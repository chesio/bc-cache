<?php

namespace BlueChip\Cache;

class Core
{
    /**
     * @var string Key of default request variant.
     */
    public const DEFAULT_REQUEST_VARIANT = '';

    /**
     * @var string Separator between URL scheme, host and port parts in cache filename
     */
    private const SCHEME_HOST_SEPARATOR = '_';

    /**
     * @var string Name of subdirectory for cache entries for URLs with file-like paths.
     */
    private const FILE_PATH_DIRNAME = '@file';

    /**
     * @var string Name of subdirectory for cache entries for URLs with directory-like paths.
     */
    private const DIRECTORY_PATH_DIRNAME = '@dir';


    /**
     * @param string $cache_dir Absolute path to root cache directory
     * @param Info $cache_info Cache information (age, size) handler
     * @param Lock $cache_lock Flock wrapper for atomic cache reading/writing
     */
    public function __construct(private string $cache_dir, private Info $cache_info, private Lock $cache_lock)
    {
    }


    /**
     * Make sure root cache directory exists or has been created and is empty and writable.
     *
     * @return bool True if root cache directory exists (or has been created successfully) and is writable and empty, false otherwise.
     */
    public function setUp(): bool
    {
        if (\is_dir($this->cache_dir)) {
            // If cache directory exists, make sure it is empty.
            try {
                self::removeDirectory($this->cache_dir, true);
            } catch (Exception $exception) {
                \trigger_error((string) $exception, E_USER_WARNING);
                return false;
            }
        } elseif (!wp_mkdir_p($this->cache_dir)) {
            \trigger_error(\sprintf('Failed to create root cache directory %s.', $this->cache_dir), E_USER_WARNING);
            return false;
        }

        if (!\is_writable($this->cache_dir)) {
            \trigger_error(\sprintf('Root cache directory %s is not writable!', $this->cache_dir), E_USER_WARNING);
            return false;
        }

        return true;
    }


    /**
     * Flush the cache and remove the root cache directory.
     *
     * @return bool True on success, false on otherwise.
     */
    public function tearDown(): bool
    {
        return $this->flush(true);
    }


    /**
     * @return string[] Filtered list of request variants.
     */
    public function getRequestVariants(): array
    {
        return apply_filters(
            Hooks::FILTER_REQUEST_VARIANTS,
            [Core::DEFAULT_REQUEST_VARIANT => __('Default', 'bc-cache')]
        );
    }


    /**
     * Flush entire cache.
     *
     * @param bool $tear_down Not only flush cache entries, but remove cache directory as well.
     *
     * @return bool True on success (there has been no error), false otherwise.
     */
    public function flush(bool $tear_down = false): bool
    {
        // Wait for exclusive lock.
        if (!$this->cache_lock->acquire(true)) {
            // Exclusive lock could not be acquired, bail.
            return false;
        }

        if (!\is_dir($this->cache_dir)) {
            // Treat as successful cache flush.
            $this->cache_info->reset()->write();
            // Unlock cache for other operations.
            $this->cache_lock->release();
            // Cache directory does not exist, therefore report success.
            return true;
        }

        try {
            // Remove cache directory - if not uninstalling, remove contents only.
            self::removeDirectory($this->cache_dir, !$tear_down);
            // Reset cache age and size.
            $this->cache_info->reset();
            // :)
            return true;
        } catch (Exception $exception) {
            // Clear information about cache size, it might be corrupted.
            $this->cache_info->unsetSize();
            // Trigger a warning and let WordPress handle it.
            \trigger_error((string) $exception, E_USER_WARNING);
            // :(
            return false;
        } finally {
            // Persist cache info changes.
            $this->cache_info->write();
            // Always clear stat cache.
            \clearstatcache();
            // Unlock cache for other operations.
            $this->cache_lock->release();
            // Signal that cache has been flushed.
            do_action(Hooks::ACTION_CACHE_FLUSHED, $tear_down);
        }
    }


    /**
     * Delete given $item from cache.
     *
     * @param Item $item
     *
     * @return bool True on success (there has been no error), false otherwise.
     */
    public function delete(Item $item): bool
    {
        try {
            // Get directory for given URL.
            $path = $this->getPath($item->getUrl());
        } catch (Exception $exception) {
            // Trigger a warning and let WordPress handle it.
            \trigger_error((string) $exception, E_USER_WARNING);
            // :(
            return false;
        }

        if (!\is_dir($path)) {
            // No cache entries for given URL not exist, so we're done.
            return true;
        }

        // Wait for exclusive lock.
        if (!$this->cache_lock->acquire(true)) {
            // Exclusive lock could not be acquired, bail.
            return false;
        }

        try {
            $bytes_deleted
                = self::deleteFile(self::getPlainFilename($path, $item->getRequestVariant()))
                + self::deleteFile(self::getGzipFilename($path, $item->getRequestVariant()))
                + self::deleteFile(self::getHtaccessFilename($path))
            ;
            \rmdir($path);
            // Update cache size.
            $this->cache_info->decrementSize($bytes_deleted);
            // :)
            return true;
        } catch (Exception $exception) {
            // I/O error - clear information about cache size, it might be no longer valid.
            $this->cache_info->unsetSize();
            // Trigger a warning and let WordPress handle it.
            \trigger_error((string) $exception, E_USER_WARNING);
            // :(
            return false;
        } finally {
            // Persist cache info changes.
            $this->cache_info->write();
            // Always clear stat cache.
            \clearstatcache();
            // Unlock cache for other operations.
            $this->cache_lock->release();
        }
    }


    /**
     * Create new cache entry: store $data for given cache $item.
     *
     * @param Item $item
     * @param string[] $headers Response HTTP headers
     * @param string $data
     *
     * @return bool True on success (there has been no error), false otherwise.
     */
    public function push(Item $item, array $headers, string $data): bool
    {
        // Try to acquire exclusive lock, but do not wait for it.
        if (!$this->cache_lock->acquire(true, true)) {
            // Exclusive lock could not be acquired immediately, so bail.
            return false;
        }

        try {
            // Make directory for given URL.
            $path = $this->makeDirectory($item->getUrl());
        } catch (Exception $exception) {
            // Unlock cache for other operations.
            $this->cache_lock->release();
            // Trigger a warning and let WordPress handle it.
            \trigger_error((string) $exception, E_USER_WARNING);
            // :(
            return false;
        }

        try {
            // Write cache date to disk, get number of bytes written.
            $bytes_written = self::writeFile(self::getPlainFilename($path, $item->getRequestVariant()), $data);
            if (($gzip = \gzencode($data, 9)) !== false) {
                $bytes_written += self::writeFile(self::getGzipFilename($path, $item->getRequestVariant()), $gzip);
            }
            $bytes_written += self::writeFile(
                self::getHtaccessFilename($path),
                self::prepareHtaccessFile($headers)
            );
            // Increment cache size.
            $this->cache_info->incrementSize($bytes_written);
            // :)
            return true;
        } catch (Exception $exception) {
            // Clear information about cache size, it might be corrupted.
            $this->cache_info->unsetSize();
            // Trigger a warning and let WordPress handle it.
            \trigger_error((string) $exception, E_USER_WARNING);
            // :(
            return false;
        } finally {
            // Update cache info.
            $this->cache_info->write();
            // Always clear stat cache.
            \clearstatcache();
            // Unlock cache for other operations.
            $this->cache_lock->release();
        }
    }


    /**
     * Get "age" of the cache.
     *
     * @return int|null Time (as Unix timestamp) when the cache has been fully flushed or null if unknown.
     */
    public function getAge(): ?int
    {
        return $this->cache_info->getAge();
    }


    /**
     * Get size of cache data.
     *
     * @param bool $precise Calculate the size from disk (ignore any cached information).
     *
     * @return int|null Size of cache data or null if size cannot be determined.
     */
    public function getSize(bool $precise = false): ?int
    {
        if (!$precise && (($cache_size = $this->cache_info->getSize()) !== null)) {
            return $cache_size;
        }

        // Wait for non-exclusive lock.
        if (!$this->cache_lock->acquire(false)) {
            // Non-exclusive lock could not be acquired.
            return null;
        }

        // Read cache size from disk...
        $cache_size = \is_dir($this->cache_dir) ? self::getFilesSize($this->cache_dir) : 0;
        // ...update cache information...
        $this->cache_info->setSize($cache_size)->write();
        // ...unlock cache for other operations...
        $this->cache_lock->release();
        // ...and return the size:
        return $cache_size;
    }


    /**
     * Check whether $item is in cache.
     *
     * @internal Check is based on presence of plain file only (gzip is optional, so cannot be reliably used).
     *
     * @param Item $item
     *
     * @return bool True if $item is in cache, false otherwise.
     */
    public function has(Item $item): bool
    {
        return \is_readable(self::getPlainFilename($this->getPath($item->getUrl()), $item->getRequestVariant()));
    }


    /**
     * Get cache state information.
     *
     * @return ListTableItem[]|null List of cache entries read from cache directory or null in case of I/O error.
     */
    public function inspect(): ?array
    {
        if (!\is_dir($this->cache_dir)) {
            return [];
        }

        // Wait for non-exclusive lock.
        if (!$this->cache_lock->acquire(false)) {
            // Non-exclusive lock could not be acquired.
            return null;
        }

        // Get cache sizes.
        $cache_sizes = self::getCacheSizes($this->cache_dir, \array_keys($this->getRequestVariants()));

        // Unlock cache for other operations.
        $this->cache_lock->release();

        $state = [];

        foreach ($cache_sizes as $id => $item) {
            try {
                $url = $this->getUrl($item['path']);
            } catch (Exception $exception) {
                // Trigger a warning and let WordPress handle it.
                \trigger_error((string) $exception, E_USER_WARNING);
                // Skip this item.
                continue;
            }

            $state[] = new ListTableItem(
                \substr($id, \strlen($this->cache_dir . DIRECTORY_SEPARATOR)), // make ID relative to cache directory
                $url,
                $item['request_variant'],
                self::getCreationTimestamp($item['path'], $item['request_variant']),
                $item['total_disk_size'],
                $item['plain_size'],
                $item['gzip_size'],
                $item['htaccess_size'],
            );
        }

        return $state;
    }


    /**
     * Get time (as Unix timestamp) of creation of cache entry under given $path.
     *
     * @param string $path Path to cache directory without trailing directory separator.
     * @param string $request_variant [optional] Request variant (default empty).
     *
     * @return int|null Time (as Unix timestamp) of creation of cache entry under given $path or null in case of I/O error.
     */
    private static function getCreationTimestamp(string $path, string $request_variant = self::DEFAULT_REQUEST_VARIANT): ?int
    {
        return \filemtime(self::getPlainFilename($path, $request_variant)) ?: null;
    }


    /**
     * Return total size of all regular files in given directory and its subdirectories.
     *
     * @param string $dirname
     *
     * @return int Total size of all regular files in given directory and its subdirectories.
     *
     * @throws Exception If $dirname does not exists or is not a directory.
     */
    private static function getFilesSize(string $dirname): int
    {
        if (!\is_dir($dirname)) {
            throw new Exception("{$dirname} is not a directory!");
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirname)
        );

        $size = 0;
        foreach ($it as $fileinfo) {
            if ($fileinfo->isFile()) {
                $size += $fileinfo->getSize();
            }
        }

        return $size;
    }


    /**
     * Return an array with cache size information for given directory and all its subdirectories.
     *
     * @param string $dirname
     * @param string[] $request_variants
     *
     * @return array[] List of cache entries with following data: `path` (dirname), `request_variant`, `total_disk_size`, `plain_size`, `gzip_size` and `htaccess_size`.
     *
     * @throws Exception When $dirname is not a directory.
     */
    private static function getCacheSizes(string $dirname, array $request_variants): array
    {
        if (!\is_dir($dirname)) {
            throw new Exception("{$dirname} is not a directory!");
        }

        $it = new \DirectoryIterator($dirname);

        // An array of all cache entries (path + request variant) and their sizes.
        $entries = [];

        foreach ($it as $fileinfo) {
            if (!$fileinfo->isDir() || $fileinfo->isDot()) {
                // Skip non-directories and '.' and '..' directories.
                continue;
            }

            $basename = $fileinfo->getBasename();
            $pathname = $fileinfo->getPathname();

            if (($basename === self::DIRECTORY_PATH_DIRNAME) || ($basename === self::FILE_PATH_DIRNAME)) {
                // Directory holds cache entry, get its sizes.
                $entries += self::getCacheEntrySizes($pathname, $request_variants);
            } else {
                // Recurse in the subdirectory.
                $entries += self::getCacheSizes($pathname, $request_variants);
            }
        }

        return $entries;
    }


    /**
     * Return an array with cache size information for all request variants in given cache entry directory.
     *
     * @param string $dirname Path to cache entry directory (must end either with "/@dir" or "/@file").
     * @param string[] $request_variants
     *
     * @return array[] List of cache entries with following data: `path` (dirname), `request_variant`, `total_disk_size`, `plain_size`, `gzip_size` and `htaccess_size`.
     *
     * @throws Exception
     */
    private static function getCacheEntrySizes(string $dirname, array $request_variants): array
    {
        // .htaccess file is shared for all request variants, so its size must be counted only once in total disk size.
        $htaccessFilename = self::getHtaccessFilename($dirname);
        $request_variant_htaccess_size = $htaccess_size = \is_file($htaccessFilename) ? (\filesize($htaccessFilename) ?: 0) : 0;

        $entries = [];

        // Loop through all request variants and grab size information.
        foreach ($request_variants as $request_variant) {
            $request_variant_plain_size = $request_variant_gzip_size = 0;

            $plainFilename = self::getPlainFilename($dirname, $request_variant);
            if (\is_file($plainFilename)) {
                $request_variant_plain_size = \filesize($plainFilename) ?: 0;
            }

            $gzipFilename = self::getGzipFilename($dirname, $request_variant);
            if (\is_file($gzipFilename)) {
                $request_variant_gzip_size = \filesize($gzipFilename) ?: 0;
            }

            if (($request_variant_plain_size + $request_variant_gzip_size) > 0) {
                $entries[self::getPlainFilename($dirname, $request_variant)] = [
                    'path'  => $dirname,
                    'request_variant' => $request_variant,
                    'total_disk_size' => $request_variant_plain_size + $request_variant_gzip_size + $htaccess_size,
                    'plain_size' => $request_variant_plain_size,
                    'gzip_size' => $request_variant_gzip_size,
                    'htaccess_size' => $request_variant_htaccess_size,
                ];

                // Calculate .htaccess size in total disk size only once per directory.
                $htaccess_size = 0;
            }
        }

        return $entries;
    }


    /**
     * @param string $path Absolute path to cache directory without trailing directory separator.
     * @param string $request_variant [optional] Request variant (default empty).
     *
     * @return string Path to plain cache file for given $path and $request variant.
     */
    private static function getPlainFilename(string $path, string $request_variant = self::DEFAULT_REQUEST_VARIANT): string
    {
        return $path . DIRECTORY_SEPARATOR . "index{$request_variant}";
    }


    /**
     * @param string $path Absolute path to cache directory without trailing directory separator.
     * @param string $request_variant [optional] Request variant (default empty).
     *
     * @return string Path to gzipped cache file for given $path and $request variant.
     */
    private static function getGzipFilename(string $path, string $request_variant = self::DEFAULT_REQUEST_VARIANT): string
    {
        return $path . DIRECTORY_SEPARATOR . "index{$request_variant}.gz";
    }


    /**
     * @param string $path Absolute path to directory of cache entry without trailing directory separator.
     *
     * @return string Path to .htaccess file for given $path.
     */
    private static function getHtaccessFilename(string $path): string
    {
        return $path . DIRECTORY_SEPARATOR . ".htaccess";
    }


    /**
     * Return path to cache directory for given URL.
     *
     * @see self::getUrl()
     *
     * @param string $url
     *
     * @return string
     *
     * @throws Exception
     */
    private function getPath(string $url): string
    {
        $url_parts = \parse_url($url);

        $url_path = $url_parts['path'] ?? '';

        $path = \implode([
            $this->cache_dir,
            DIRECTORY_SEPARATOR,
            $url_parts['scheme'],
            self::SCHEME_HOST_SEPARATOR,
            $url_parts['host'],
            trailingslashit($url_path),
            // URL path ends with slash? Yes: treat as directory path. No: treat as file path.
            \str_ends_with($url_path, '/') ? self::DIRECTORY_PATH_DIRNAME : self::FILE_PATH_DIRNAME,
        ]);

        $normalized_path = self::normalizePath($path);

        // Make sure that normalized path still points to a subdirectory of root cache directory.
        if (!\str_starts_with($normalized_path, $this->cache_dir . DIRECTORY_SEPARATOR)) {
            throw new Exception("Could not retrieve a valid cache filename from URL {$url}.");
        }

        return $normalized_path;
    }


    /**
     * Attempt to reconstruct URL of page cached under given cache directory.
     *
     * @see self::getPath()
     *
     * @param string $path
     *
     * @return string
     *
     * @throws Exception
     */
    private function getUrl(string $path): string
    {
        // Just in case.
        $normalized_path = self::normalizePath($path);

        // The path must point to a subdirectory of root cache directory.
        if (!\str_starts_with($normalized_path, $this->cache_dir . DIRECTORY_SEPARATOR)) {
            throw new Exception("Path {$path} is not a valid cache path.");
        }

        // Strip the path to BC Cache directory from $path and break it into scheme and host + path parts.
        $parts = \explode(self::SCHEME_HOST_SEPARATOR, \substr($normalized_path, \strlen($this->cache_dir . DIRECTORY_SEPARATOR)), 2);

        if (\count($parts) !== 2) {
            throw new Exception("Could not retrieve a valid URL from cache path {$path}.");
        }

        // Break host + path into host and path.
        $subparts = \explode(DIRECTORY_SEPARATOR, $parts[1], 2);

        if (\count($subparts) !== 2) {
            throw new Exception("Could not retrieve a valid URL from cache path {$path}.");
        }

        $path = DIRECTORY_SEPARATOR . $subparts[1];
        if (\str_ends_with($path, DIRECTORY_SEPARATOR . self::FILE_PATH_DIRNAME)) {
            // Strip file path dirname including trailing directory separator.
            $path = \substr($path, 0, -1 * \strlen(DIRECTORY_SEPARATOR . self::FILE_PATH_DIRNAME));
        } elseif (\str_ends_with($path, DIRECTORY_SEPARATOR . self::DIRECTORY_PATH_DIRNAME)) {
            // Strip directory path dirname, but keep trailing directory separator.
            $path = \substr($path, 0, -1 * \strlen(self::DIRECTORY_PATH_DIRNAME));
        }

        return $parts[0] . '://' . $subparts[0] . \str_replace(DIRECTORY_SEPARATOR, '/', $path);
    }


    /**
     * Create directory for given URL and return its path.
     *
     * @param string $url
     *
     * @return string
     *
     * @throws Exception
     */
    private function makeDirectory(string $url): string
    {
        $path = $this->getPath($url);

        /* Create directory */
        if (!wp_mkdir_p($path)) {
            throw new Exception("Unable to create directory {$path}.");
        }

        return $path;
    }


    /**
     * Remove given directory including all subdirectories.
     *
     * @param string $dirname
     *
     * @param bool $contents_only If true, only contents of directory $dirname are removed, but not the directory itself.
     *
     * @throws Exception
     */
    private static function removeDirectory(string $dirname, bool $contents_only = false): void
    {
        if (!\is_dir($dirname)) {
            throw new Exception("{$dirname} is not a directory!");
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirname, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $fileinfo) {
            // Get full path to file/directory.
            $path = $fileinfo->getPathname();

            if ($fileinfo->isDir() && !$fileinfo->isLink()) {
                if (!\rmdir($path)) {
                    throw new Exception("Could not remove directory {$path}.");
                }
            } else {
                if (!\unlink($path)) {
                    throw new Exception("Could not remove file {$path}.");
                }
            }
        }

        // Optionally, remove the directory itself.
        if (!$contents_only && !\rmdir($dirname)) {
            throw new Exception("Could not remove {$dirname} directory.");
        }
    }


    /**
     * Normalize given non-empty absolute path:
     * - sanitize directory separators
     * - drop empty segments
     * - resolve relative segments (".." and ".").
     *
     * @link https://www.php.net/manual/en/function.realpath.php#84012
     *
     * @param string $path Non-empty *absolute* path to normalize.
     *
     * @return string Normalized absolute path without any trailing directory separator.
     *
     * @throws Exception In case of attempt to normalize empty or relative path.
     */
    private static function normalizePath(string $path): string
    {
        if ($path === '') {
            throw new Exception('Cannot normalize an empty path.');
        }

        // Sanitize directory separators.
        $sanitized = \str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Break path into directory parts.
        $parts = \explode(DIRECTORY_SEPARATOR, $sanitized);

        // Retrieve first segment - if path is absolute, then it must be an empty string.
        if (\array_shift($parts) !== '') {
            throw new Exception('Cannot normalize a relative path.');
        }

        $absolutes = [];

        foreach ($parts as $part) {
            if (($part === '') || ($part === '.')) {
                continue;
            }

            if ($part === '..') {
                \array_pop($absolutes);
            } else {
                \array_push($absolutes, $part);
            }
        }

        return DIRECTORY_SEPARATOR . \implode(DIRECTORY_SEPARATOR, $absolutes);
    }


    /**
     * @param string $filename
     *
     * @return int Number of bytes deleted (file size).
     *
     * @throws Exception
     */
    private static function deleteFile(string $filename): int
    {
        if (!\file_exists($filename)) {
            // Deleting non-existing file removes 0 bytes from disk.
            return 0;
        }

        if (!\is_file($filename)) {
            throw new Exception("Could not delete a non-regular file {$filename}.");
        }

        if (($size = \filesize($filename)) === false) {
            throw new Exception("Failed to get size of file {$filename}.");
        }

        if (!\unlink($filename)) {
            throw new Exception("Failed to delete file {$filename}.");
        }

        return $size;
    }


    /**
     * @param string $filename
     * @param string $data
     *
     * @return int Number of bytes written to file.
     *
     * @throws Exception
     */
    private static function writeFile(string $filename, string $data): int
    {
        if (!$handle = \fopen($filename, 'wb')) {
            throw new Exception("Could not open file {$filename} for writing.");
        }

        /* Write */
        $status = \fwrite($handle, $data);
        \fclose($handle);

        if ($status === false) {
            throw new Exception("Could not write data to file {$filename}.");
        }

        return $status;

        // TODO: Set file permissions like Cachify do?
    }


    /**
     * Return contents of `.htaccess` file with ruleset for provided headers and optional ForceType directive.
     *
     * @internal ForceType is only set if MIME type can be detected from $headers.
     *
     * @param string[] $headers Headers to set
     *
     * @return string
     */
    private static function prepareHtaccessFile(array $headers): string
    {
        $htaccess = [];

        $mime_type = Utils::getResponseMimeType($headers);
        if ($mime_type) {
            $htaccess[] = "ForceType $mime_type";
        }

        // Sanitize headers.
        $headers = \array_filter(
            $headers,
            fn (string $header): bool => \str_contains($header, ':')
        );

        // Parse headers into name (type) and value parts.
        $headers = \array_map(
            fn (string $header): array => \array_map('trim', \explode(':', $header, 2)),
            $headers
        );

        if ($headers !== []) {
            $htaccess[] = '<IfModule mod_headers.c>';
            foreach ($headers as [$name, $value]) {
                // Make sure that "Link" headers are appended.
                $directive = \strtolower($name) === 'link' ? 'append' : 'set';

                $htaccess[] = \sprintf('Header %s %s "%s"', $directive, $name, \str_replace('"', '\"', $value));
            }
            $htaccess[] = '</IfModule>';
        }

        return \implode(PHP_EOL, $htaccess);
    }
}

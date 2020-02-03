<?php

namespace BlueChip\Cache;

class Core
{
    /**
     * @var string Key of default request variant.
     */
    public const DEFAULT_REQUEST_VARIANT = '';


    /**
     * @var string Path to root cache directory
     */
    private $cache_dir;

    /**
     * @var \BlueChip\Cache\Info Cache information handler
     */
    private $cache_info;

    /**
     * @var \BlueChip\Cache\Lock Flock wrapper for atomic cache reading/writing
     */
    private $cache_lock;


    /**
     * @param string $cache_dir Path to root cache directory
     * @param \BlueChip\Cache\Info $cache_info Cache information (age, size) handler
     * @param \BlueChip\Cache\Lock $cache_lock Flock wrapper for atomic cache reading/writing
     */
    public function __construct(string $cache_dir, Info $cache_info, Lock $cache_lock)
    {
        $this->cache_dir = $cache_dir;
        $this->cache_info = $cache_info;
        $this->cache_lock = $cache_lock;
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
            } catch (Exception $e) {
                \trigger_error($e, E_USER_WARNING);
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

        // With respect to cache information, setup equals cache flush.
        $this->cache_info->reset()->write();

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
    public static function getRequestVariants(): array
    {
        return apply_filters(
            Hooks::FILTER_REQUEST_VARIANTS,
            [Core::DEFAULT_REQUEST_VARIANT => __('Default', 'bc-cache')]
        );
    }


    /**
     * Flush entire cache.
     *
     * @param bool $uninstall Not only flush cache entries, but remove cache directory as well.
     * @return bool True on success (there has been no error), false otherwise.
     */
    public function flush(bool $uninstall = false): bool
    {
        // Wait for exclusive lock.
        if (!$this->lockCache(true)) {
            // Exclusive lock could not be acquired, bail.
            return false;
        }

        if (!\is_dir($this->cache_dir)) {
            // Treat as successful cache flush.
            $this->cache_info->reset()->write();
            // Unlock cache for other operations.
            $this->unlockCache();
            // Cache directory does not exist, therefore report success.
            return true;
        }

        try {
            // Remove cache directory - if not uninstalling, remove contents only.
            self::removeDirectory($this->cache_dir, !$uninstall);
            // Reset cache age and size.
            $this->cache_info->reset();
            // :)
            return true;
        } catch (Exception $e) {
            // Clear information about cache size, it might be corrupted.
            $this->cache_info->unsetSize();
            // Trigger a warning and let WordPress handle it.
            \trigger_error($e, E_USER_WARNING);
            // :(
            return false;
        } finally {
            // Persist cache info changes.
            $this->cache_info->write();
            // Always clear stat cache.
            \clearstatcache();
            // Unlock cache for other operations.
            $this->unlockCache();
        }
    }


    /**
     * Delete data for given URL from cache.
     *
     * @param string $url
     * @param string $request_variant [optional] Request variant to delete.
     * @return bool True on success (there has been no error), false otherwise.
     */
    public function delete(string $url, string $request_variant = self::DEFAULT_REQUEST_VARIANT): bool
    {
        try {
            // Get directory for given URL.
            $path = $this->getPath($url);
        } catch (Exception $e) {
            // Trigger a warning and let WordPress handle it.
            \trigger_error($e, E_USER_WARNING);
            // :(
            return false;
        }

        if (!\file_exists($path)) {
            // No cache entries for given URL not exist, so we're done.
            return true;
        }

        // Wait for exclusive lock.
        if (!$this->lockCache(true)) {
            // Exclusive lock could not be acquired, bail.
            return false;
        }

        try {
            $bytes_deleted
                = self::deleteFile(self::getHtmlFilename($path, $request_variant))
                + self::deleteFile(self::getGzipFilename($path, $request_variant))
            ;
            // Update cache size.
            $this->cache_info->decrementSize($bytes_deleted);
            // :)
            return true;
        } catch (Exception $e) {
            // I/O error - clear information about cache size, it might be no longer valid.
            $this->cache_info->unsetSize();
            // Trigger a warning and let WordPress handle it.
            \trigger_error($e, E_USER_WARNING);
            // :(
            return false;
        } finally {
            // Persist cache info changes.
            $this->cache_info->write();
            // Always clear stat cache.
            \clearstatcache();
            // Unlock cache for other operations.
            $this->unlockCache();
        }
    }


    /**
     * Store data for given URL in cache.
     *
     * @param string $url
     * @param string $data
     * @param string $request_variant [optional] Request variant to store the data under.
     * @return bool True on success (there has been no error), false otherwise.
     */
    public function push(string $url, string $data, string $request_variant = self::DEFAULT_REQUEST_VARIANT): bool
    {
        // Try to acquire exclusive lock, but do not wait for it.
        if (!$this->lockCache(true, true)) {
            // Exclusive lock could not be acquired immediately, so bail.
            return false;
        }

        try {
            // Make directory for given URL.
            $path = $this->makeDirectory($url);
        } catch (Exception $e) {
            // Unlock cache for other operations.
            $this->unlockCache();
            // Trigger a warning and let WordPress handle it.
            \trigger_error($e, E_USER_WARNING);
            // :(
            return false;
        }

        try {
            // Write cache date to disk, get number of bytes written.
            $bytes_written = self::writeFile(self::getHtmlFilename($path, $request_variant), $data);
            if (($gzip = \gzencode($data, 9)) !== false) {
                $bytes_written += self::writeFile(self::getGzipFilename($path, $request_variant), $gzip);
            }
            // Increment cache size.
            $this->cache_info->incrementSize($bytes_written);
            // :)
            return true;
        } catch (Exception $e) {
            // Clear information about cache size, it might be corrupted.
            $this->cache_info->unsetSize();
            // Trigger a warning and let WordPress handle it.
            \trigger_error($e, E_USER_WARNING);
            // :(
            return false;
        } finally {
            // Update cache info.
            $this->cache_info->write();
            // Always clear stat cache.
            \clearstatcache();
            // Unlock cache for other operations.
            $this->unlockCache();
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
     * @return int|null Size of cache data or null if size cannot be determined.
     */
    public function getSize(bool $precise = false): ?int
    {
        if (!$precise && (($cache_size = $this->cache_info->getSize()) !== null)) {
            return $cache_size;
        }

        // Wait for non-exclusive lock.
        if (!$this->lockCache(false)) {
            // Non-exclusive lock could not be acquired.
            return null;
        }

        // Read cache size from disk...
        $cache_size = \is_dir($this->cache_dir) ? self::getFilesSize($this->cache_dir) : 0;
        // ...update cache information...
        $this->cache_info->setSize($cache_size)->write();
        // ...unlock cache for other operations...
        $this->unlockCache();
        // ...and return the size:
        return $cache_size;
    }


    /**
     * Get cache state information.
     *
     * @param string[] $request_variants List of all request variants to inspect.
     * @return object[] List of all cache entries with data about `entry_id`, `size`, `url`, `request_variant` and creation `timestamp`.
     */
    public function inspect(array $request_variants): ?array
    {
        if (!\is_dir($this->cache_dir)) {
            return [];
        }

        // Wait for non-exclusive lock.
        if (!$this->lockCache(false)) {
            // Non-exclusive lock could not be acquired.
            return null;
        }

        // Get cache sizes.
        $cache_sizes = self::getCacheSizes($this->cache_dir, $request_variants);

        // Unlock cache for other operations.
        $this->unlockCache();

        $state = [];

        foreach ($cache_sizes as $id => $item) {
            try {
                $url = $this->getUrl($item['path']);
            } catch (Exception $e) {
                // Trigger a warning and let WordPress handle it.
                \trigger_error($e, E_USER_WARNING);
                $url = null;
            }

            $state[] = (object) [
                'entry_id' => \substr($id, \strlen($this->cache_dir . DIRECTORY_SEPARATOR)), // make ID relative to cache directory
                'url' => $url,
                'request_variant' => $item['request_variant'],
                'timestamp' => self::getCreationTimestamp($item['path'], $item['request_variant']),
                'size' => $item['html_size'] + $item['gzip_size'],
                'html_size' => $item['html_size'],
                'gzip_size' => $item['gzip_size'],
            ];
        }

        return $state;
    }


    /**
     * @param bool $exclusive If true, require exclusive lock. If false, require shared lock.
     * @param bool $non_blocking [optional] If true, do not wait for lock, but fail immediately.
     * @return bool True on success, false on failure.
     */
    private function lockCache(bool $exclusive, bool $non_blocking = false): bool
    {
        return apply_filters(Hooks::FILTER_DISABLE_CACHE_LOCKING, false) ? true : $this->cache_lock->acquire($exclusive, $non_blocking);
    }


    /**
     * @return bool True on success, false on failure.
     */
    private function unlockCache(): bool
    {
        return apply_filters(Hooks::FILTER_DISABLE_CACHE_LOCKING, false) ? true : $this->cache_lock->release();
    }


    /**
     * Get time (as Unix timestamp) of creation of cache entry under given $path.
     *
     * @param string $path Path to cache directory without trailing directory separator.
     * @param string $request_variant [optional] Request variant (default empty).
     * @return int|null Time (as Unix timestamp) of creation of cache entry under given $path or null in case of I/O error.
     */
    private static function getCreationTimestamp(string $path, string $request_variant = self::DEFAULT_REQUEST_VARIANT): ?int
    {
        return \filemtime(self::getHtmlFilename($path, $request_variant)) ?: null;
    }


    /**
     * Return total size of all regular files in given directory and its subdirectories.
     *
     * @param string $dirname
     * @return int Total size of all regular files in given directory and its subdirectories.
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
     * @return array[] List of cache entries with following data: `path` (dirname), `request_variant`, `html_size` and `gzip_size`.
     * @throws Exception
     */
    private static function getCacheSizes(string $dirname, array $request_variants): array
    {
        if (!\is_dir($dirname)) {
            throw new Exception("{$dirname} is not a directory!");
        }

        $it = new \DirectoryIterator($dirname);

        // An array of all cache entries (path + request variant) and their sizes.
        $entries = [];

        // Process any subdirectories first.
        foreach ($it as $fileinfo) {
            if ($it->isDir() && !$it->isDot()) { // Skip '.' and '..' directories.
                // Get the path.
                $subdirname = $fileinfo->getPathname();
                // Update the pool of cache sizes.
                $entries += self::getCacheSizes($subdirname, $request_variants);
            }
        }

        // Loop through all request variants and grab size information.
        foreach ($request_variants as $request_variant) {
            $request_variant_html_size = $request_variant_gzip_size = 0;

            $htmlFilename = self::getHtmlFilename($dirname, $request_variant);
            if (\is_file($htmlFilename)) {
                $request_variant_html_size = \filesize($htmlFilename) ?: 0;
            }

            $gzipFilename = self::getGzipFilename($dirname, $request_variant);
            if (\is_file($gzipFilename)) {
                $request_variant_gzip_size = \filesize($gzipFilename) ?: 0;
            }

            if (($request_variant_html_size + $request_variant_gzip_size) > 0) {
                $entries[self::getBaseFilename($dirname, $request_variant)] = [
                    'path'  => $dirname,
                    'request_variant' => $request_variant,
                    'html_size' => $request_variant_html_size,
                    'gzip_size' => $request_variant_gzip_size,
                ];
            }
        }

        return $entries;
    }


    /**
     * @param string $path Path to cache directory without trailing directory separator.
     * @param string $request_variant [optional] Request variant (default empty).
     * @return string Path to cache basename file (cache entry ID) for given $path and $request variant.
     */
    private static function getBaseFilename(string $path, string $request_variant = self::DEFAULT_REQUEST_VARIANT): string
    {
        return $path . DIRECTORY_SEPARATOR . "index{$request_variant}";
    }


    /**
     * @param string $path Path to cache directory without trailing directory separator.
     * @param string $request_variant [optional] Request variant (default empty).
     * @return string Path to gzipped cache file for given $path and $request variant.
     */
    private static function getGzipFilename(string $path, string $request_variant = self::DEFAULT_REQUEST_VARIANT): string
    {
        return $path . DIRECTORY_SEPARATOR . "index{$request_variant}.html.gz";
    }


    /**
     * @param string $path Path to cache directory without trailing directory separator.
     * @param string $request_variant [optional] Request variant (default empty).
     * @return string Path to HTML cache file for given $path and $request variant.
     */
    private static function getHtmlFilename(string $path, string $request_variant = self::DEFAULT_REQUEST_VARIANT): string
    {
        return $path . DIRECTORY_SEPARATOR . "index{$request_variant}.html";
    }


    /**
     * Return path to cache directory for given URL.
     *
     * @see self::getUrl()
     *
     * @param string $url
     * @return string
     * @throws Exception
     */
    private function getPath(string $url): string
    {
        $url_parts = wp_parse_url(trailingslashit($url));

        $path = \implode([
            $this->cache_dir,
            DIRECTORY_SEPARATOR,
            $url_parts['scheme'],
            DIRECTORY_SEPARATOR,
            $url_parts['host'],
            $url_parts['path'],
        ]);

        $normalized_path = self::normalizePath($path);

        // Make sure that normalized path still points to a subdirectory of root cache directory.
        if (\strpos($normalized_path, $this->cache_dir . DIRECTORY_SEPARATOR) !== 0) {
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
     * @return string
     * @throws Exception
     */
    private function getUrl(string $path): string
    {
        // Just in case.
        $normalized_path = self::normalizePath($path);

        // The path must point to a subdirectory of root cache directory.
        if (\strpos($normalized_path, $this->cache_dir . DIRECTORY_SEPARATOR) !== 0) {
            throw new Exception("Path {$path} is not a valid cache path.");
        }

        // Strip the path to BC Cache directory from $path and break it into scheme and host + path parts.
        $parts = \explode(DIRECTORY_SEPARATOR, \substr($normalized_path, \strlen($this->cache_dir . DIRECTORY_SEPARATOR)), 2);

        if (\count($parts) !== 2) {
            // At least scheme and host must be present.
            throw new Exception("Could not retrieve a valid URL from cache path {$path}.");
        }

        return $parts[0] . '://' . \str_replace(DIRECTORY_SEPARATOR, '/', $parts[1]) . '/';
    }


    /**
     * Create directory for given URL and return its path.
     *
     * @param string $url
     * @return string
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
     * @param bool $contents_only If true, only contents of directory $dirname are removed, but not the directory itself.
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
     * Normalize given absolute path: sanitize directory separators, resolve all empty parts as well as ".." and ".".
     *
     * @link https://secure.php.net/manual/en/function.realpath.php#84012
     *
     * @param string $path Absolute path to normalize.
     * @return string Normalized path without any trailing directory separator.
     */
    private static function normalizePath(string $path): string
    {
        // Sanitize directory separators.
        $sanitized = \str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Break path into directory parts.
        if (empty($parts = \explode(DIRECTORY_SEPARATOR, $sanitized))) {
            return '';
        }

        // Always keep the first part (even if empty) - assume absolute path.
        $absolutes = [\array_shift($parts)];

        foreach ($parts as $part) {
            if (empty($part) || $part === '.') {
                continue;
            }

            if ($part === '..') {
                \array_pop($absolutes);
            } else {
                \array_push($absolutes, $part);
            }
        }

        return \implode(DIRECTORY_SEPARATOR, $absolutes);
    }


    /**
     * @param string $filename
     * @return int Number of bytes deleted (file size).
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
     * @return int Number of bytes written to file.
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
}

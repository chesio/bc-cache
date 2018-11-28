<?php
/**
 * @package BC_Cache
 */

namespace BlueChip\Cache;

class Core
{
    /**
     * Path to root cache directory
     */
    const CACHE_DIR = WP_CONTENT_DIR . '/cache/bc-cache';

    /**
     * URL of root cache directory
     */
    const CACHE_URL = WP_CONTENT_URL . '/cache/bc-cache';

    /**
     * Name of transient used to cache cache size.
     */
    const TRANSIENT_CACHE_SIZE = 'bc-cache/transient:cache-size';


    /**
     * Initialize disk cache.
     *
     * @internal Method should be invoked in `init` hook.
     */
    public function init()
    {
        add_filter('robots_txt', [$this, 'alterRobotsTxt'], 10, 1);
    }


    /**
     * @param string $data
     * @return string
     */
    public function alterRobotsTxt(string $data): string
    {
        // Get path component of cache directory URL.
        $path = wp_parse_url(self::CACHE_URL, PHP_URL_PATH);
        // Disallow direct access to cache directory.
        return $data . PHP_EOL . sprintf('Disallow: %s/', $path) . PHP_EOL;
    }


    /**
     * Flush entire disk cache.
     *
     * @param bool $uninstall Not only flush cache entries, but remove any metadata as well.
     * @return bool True on success (there has been no error), false otherwise.
     */
    public function flush(bool $uninstall = false): bool
    {
        // Cache size is going to change...
        delete_transient(self::TRANSIENT_CACHE_SIZE);

        if (!file_exists(self::CACHE_DIR)) {
            // Cache directory does not exist, so cache must be empty.
            return true;
        }

        try {
            // Try to remove cache directory...
            self::removeDirectory(self::CACHE_DIR);
            // If not wiping everything out...
            if (!$uninstall) {
                // ...update cache size meta.
                set_transient(self::TRANSIENT_CACHE_SIZE, 0);
            }
            // :)
            return true;
        } catch (Exception $e) {
            // Trigger a warning and let WordPress handle it.
            trigger_error($e, E_USER_WARNING);
            // :(
            return false;
        } finally {
            // Always clear stat cache.
            clearstatcache();
        }
    }


    /**
     * Delete data for given URL from cache. All request variants are deleted.
     *
     * @param string $url
     * @return bool True on success (there has been no error), false otherwise.
     */
    public function delete(string $url): bool
    {
        try {
            // Get directory for given URL.
            $path = self::getPath($url);
        } catch (Exception $e) {
            // Trigger a warning and let WordPress handle it.
            trigger_error($e, E_USER_WARNING);
            // :(
            return false;
        }

        if (!file_exists($path)) {
            // No cache entries for given URL not exist, so we're done.
            return true;
        }

        // Get cache size before unlink attempts.
        $cache_size = get_transient(self::TRANSIENT_CACHE_SIZE);

        // Cache size is going to change...
        delete_transient(self::TRANSIENT_CACHE_SIZE);

        try {
            $bytes_deleted = self::removeDirectoryContents($path);
            // If cache size transient existed, set it anew with updated value.
            if ($cache_size !== false) {
                set_transient(self::TRANSIENT_CACHE_SIZE, max($cache_size - $bytes_deleted, 0));
            }
            // :)
            return true;
        } catch (Exception $e) {
            // Trigger a warning and let WordPress handle it.
            trigger_error($e, E_USER_WARNING);
            // :(
            return false;
        } finally {
            // Always clear stat cache.
            clearstatcache();
        }
    }


    /**
     * Store data for given URL in cache.
     *
     * @param string $url
     * @param string $data
     * @param string $request_variant [optional] Request variant.
     * @return bool True on success (there has been no error), false otherwise.
     */
    public function push(string $url, string $data, string $request_variant = ''): bool
    {
        try {
            // Make directory for given URL.
            $path = self::makeDirectory($url);
        } catch (Exception $e) {
            // Trigger a warning and let WordPress handle it.
            trigger_error($e, E_USER_WARNING);
            // :(
            return false;
        }

        // Get cache size before write attempts.
        $cache_size = get_transient(self::TRANSIENT_CACHE_SIZE);

        // Cache size is going to change...
        delete_transient(self::TRANSIENT_CACHE_SIZE);

        try {
            // Write cache date to disk, get number of bytes written.
            $bytes_written = self::writeFile(self::getHtmlFilename($path, $request_variant), $data);
            if (($gzip = gzencode($data, 9)) !== false) {
                $bytes_written += self::writeFile(self::getGzipFilename($path, $request_variant), $gzip);
            }
            // If cache size transient existed, set it anew with updated value.
            if ($cache_size !== false) {
                set_transient(self::TRANSIENT_CACHE_SIZE, $cache_size + $bytes_written);
            }
            // :)
            return true;
        } catch (Exception $e) {
            // Trigger a warning and let WordPress handle it.
            trigger_error($e, E_USER_WARNING);
            // :(
            return false;
        } finally {
            // Always clear stat cache.
            clearstatcache();
        }
    }


    /**
     * @param bool $precise Calculate the size from disk, ignore any transient data.
     * @return int Size of cache data.
     */
    public function getSize(bool $precise = false): int
    {
        if (!$precise && (($cache_size = get_transient(self::TRANSIENT_CACHE_SIZE)) !== false)) {
            return $cache_size;
        }

        // Read cache size from disk...
        $cache_size = is_dir(self::CACHE_DIR) ? self::getDirectorySize(self::CACHE_DIR) : 0;
        // ...update the transient...
        set_transient(self::TRANSIENT_CACHE_SIZE, $cache_size);
        // ...and return it:
        return $cache_size;
    }


    /**
     * Get cache state information.
     *
     * @return array List of all cache entries with information about `relative_path`, `size`, `url` and creation `timestamp`.
     */
    public function inspect(): array
    {
        $path = self::getPath(get_home_url(null, '/'));

        if (!is_dir($path)) {
            // The cache seems to be empty.
            return [];
        }

        // Get directory sizes as base data.
        // Remove items (directories) that only contain other directories, but have no (cache) files themselves.
        $items = array_filter(self::getDirectorySizes($path), function (array $item): bool { return $item['own_size'] > 0; });

        $state = [];

        foreach ($items as $path => $item) {
            $state[$path] = [
                'relative_path' => substr($path, strlen(self::CACHE_DIR . DIRECTORY_SEPARATOR)),
                'size' => $item['own_size'],
                'timestamp' => self::getCreationTimestamp($path),
                'url' => self::getUrl($path),
            ];
        }

        return $state;
    }


    /**
     * @param string $path Path to cache directory without trailing directory separator.
     * @param string $request_variant [optional] Request variant (default empty).
     * @return int Time (as Unix timestamp) of when given cache directory has been created.
     */
    private static function getCreationTimestamp(string $path, string $request_variant = ''): int
    {
        return filemtime(self::getHtmlFilename($path, $request_variant)) ?: 0;
    }


    /**
     * @param string $dirname
     * @return int Total size of all files in given directory and its subdirectories.
     * @throws Exception If $dirname does not exists or is not a directory.
     */
    private static function getDirectorySize(string $dirname): int
    {
        if (!is_dir($dirname)) {
            throw new Exception("{$dirname} is not a directory!");
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirname)
        );

        $size = 0;
        foreach ($it as $fileinfo) {
            $size += $fileinfo->getSize();
        }

        return $size;
    }


    /**
     * Return an array with size information for given directory and all its subdirectories.
     *
     * @param string $dirname
     * @return array
     * @throws Exception
     */
    private static function getDirectorySizes(string $dirname): array
    {
        if (!is_dir($dirname)) {
            throw new Exception("{$dirname} is not a directory!");
        }

        $it = new \DirectoryIterator($dirname);

        // An array of all sizes.
        $sizes = [];

        $own_size = 0; // size of files in the directory
        $total_size = 0; // size of files in the directory and all subdirectories

        foreach ($it as $fileinfo) {
            if ($it->isDot()) {
                // Skip '.' and '..' directories.
                continue;
            } elseif ($it->isDir()) {
                // Get the path.
                $subdirname = $fileinfo->getPathname();
                // Update the pool of directory sizes.
                $sizes += self::getDirectorySizes($subdirname);
                // Update the total of current directory with total of current subdirectory.
                $total_size += $sizes[$subdirname]['total_size'];
            } elseif ($it->isFile()) {
                // Update the size of current directory itself.
                $own_size += $fileinfo->getSize();
            }
        }

        // Add directory size to total size.
        $total_size += $own_size;

        $sizes[$dirname] = [
            'path'  => $dirname,
            'own_size'  => $own_size,
            'total_size' => $total_size,
        ];

        return $sizes;
    }


    /**
     * @param string $path Path to cache directory without trailing directory separator.
     * @param string $request_variant [optional] Request variant (default empty).
     * @return string Path to gzipped cache file for $path.
     */
    private static function getGzipFilename(string $path, string $request_variant = ''): string
    {
        return $path . DIRECTORY_SEPARATOR . "index{$request_variant}.html.gz";
    }


    /**
     * @param string $path Path to cache directory without trailing directory separator.
     * @param string $request_variant [optional] Request variant (default empty).
     * @return string Path to HTML cache file for $path.
     */
    private static function getHtmlFilename(string $path, string $request_variant = ''): string
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
    private static function getPath(string $url): string
    {
        $url_parts = wp_parse_url($url);

        $path = implode([
            self::CACHE_DIR,
            DIRECTORY_SEPARATOR,
            $url_parts['scheme'],
            DIRECTORY_SEPARATOR,
            $url_parts['host'],
            $url_parts['path'],
        ]);

        $normalized_path = self::normalizePath($path);

        // Make sure that normalized path still points to a subdirectory of root cache directory.
        if (strpos($normalized_path, self::CACHE_DIR . DIRECTORY_SEPARATOR) !== 0) {
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
    private static function getUrl(string $path): string
    {
        // Just in case.
        $normalized_path = self::normalizePath($path);

        // The path must point to a subdirectory of root cache directory.
        if (strpos($normalized_path, self::CACHE_DIR . DIRECTORY_SEPARATOR) !== 0) {
            throw new Exception("Path {$path} is not a valid cache path.");
        }

        // Strip the path to BC Cache directory from $path and break it into scheme and host + path parts.
        $parts = explode(DIRECTORY_SEPARATOR, substr($normalized_path, strlen(self::CACHE_DIR . DIRECTORY_SEPARATOR)), 2);

        if (count($parts) !== 2) {
            // At least scheme and host must be present.
            throw new Exception("Could not retrieve a valid URL from cache path {$path}.");
        }

        return $parts[0] . '://' . str_replace(DIRECTORY_SEPARATOR, '/', $parts[1]) . '/';
    }


    /**
     * Create directory for given URL and return its path.
     *
     * @param string $url
     * @return string
     * @throws Exception
     */
    private static function makeDirectory(string $url): string
    {
        $path = self::getPath($url);

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
     * @throws Exception
     */
    private static function removeDirectory(string $dirname)
    {
        if (!is_dir($dirname)) {
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
                if (!rmdir($path)) {
                    throw new Exception("Could not remove directory {$path}.");
                }
            } else {
                if (!unlink($path)) {
                    throw new Exception("Could not remove file {$path}.");
                }
            }
        }

        // Remove the directory itself.
        if (!rmdir($dirname)) {
            throw new Exception("Could not remove {$dirname} directory.");
        }
    }


    /**
     * Wipe out any files in given directory (subdirectories are not affected).
     *
     * @param string $dirname
     * @return int Total size of files removed from directory.
     * @throws Exception
     */
    private static function removeDirectoryContents(string $dirname): int
    {
        if (!is_dir($dirname)) {
            throw new Exception("{$dirname} is not a directory!");
        }

        $it = new \DirectoryIterator($dirname);

        $bytes_deleted = 0;

        foreach ($it as $fileinfo) {
            if (!$it->isFile()) {
                // Skip any non-files.
                continue;
            }

            // Update the size of deleted files.
            $bytes_deleted += $fileinfo->getSize();

            // Get full path to the file
            $path = $fileinfo->getPathname();

            if (!unlink($path)) {
                throw new Exception("Could not remove file {$path}.");
            }
        }

        return $bytes_deleted;
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
        $sanitized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Break path into directory parts.
        if (empty($parts = explode(DIRECTORY_SEPARATOR, $sanitized))) {
            return '';
        }

        // Always keep the first part (even if empty) - assume absolute path.
        $absolutes = [array_shift($parts)];

        foreach ($parts as $part) {
            if (empty($part) || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($absolutes);
            } else {
                array_push($absolutes, $part);
            }
        }

        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }


    /**
     * @param string $filename
     * @param string $data
     * @return int Number of bytes written to file.
     * @throws Exception
     */
    private static function writeFile(string $filename, string $data): int
    {
        if (!$handle = fopen($filename, 'wb')) {
            throw new Exception("Could not open file {$filename} for writing.");
        }

        /* Write */
        $status = fwrite($handle, $data);
        fclose($handle);

        if ($status === false) {
            throw new Exception("Could not write data to file {$filename}.");
        }

        return $status;

        // TODO: Set file permissions like Cachify do?
    }
}

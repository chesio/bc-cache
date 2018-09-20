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
     * Initialize disk cache.
     *
     * @internal Method should be invoked in `init` hook.
     */
    public function init()
    {
        add_filter('robots_txt', [$this, 'alterRobotsTxt'], 10, 1);
    }


    /**
     * Flush entire disk cache.
     *
     * @throws Exception
     */
    public function flush()
    {
        if (file_exists(self::CACHE_DIR)) {
            self::removeDirectory(self::CACHE_DIR);
            clearstatcache();
        }
    }


    /**
     * Delete data for given URL from cache.
     *
     * @param string $url
     * @param array $request_variants [optional] List of all request variants to delete for given $url.
     * @throws Exception
     */
    public function delete(string $url, array $request_variants = [''])
    {
        $path = self::getPath($url);

        foreach ($request_variants as $request_variant) {
            $html_filename = self::getHtmlFilename($path, $request_variant);
            $gzip_filename = self::getGzipFilename($path, $request_variant);

            if (file_exists($html_filename) && !unlink($html_filename)) {
                throw new Exception("Could not remove file {$html_filename}.");
            }
            if (file_exists($gzip_filename) && !unlink($gzip_filename)) {
                throw new Exception("Could not remove file {$gzip_filename}.");
            }
        }

        // TODO: Possibly return size of deleted files to update cache size stored in transient.

        clearstatcache();
    }


    /**
     * Store data for given URL in cache.
     *
     * @param string $url
     * @param string $data
     * @param string $request_variant [optional] Request variant.
     * @return int
     * @throws Exception
     */
    public function push(string $url, string $data, string $request_variant = ''): int
    {
        $path = self::getPath($url);

        /* Create directory */
        if (!wp_mkdir_p($path)) {
            throw new Exception("Unable to create directory {$path}.");
        }

        $bytes_written = self::writeFile(self::getHtmlFilename($path, $request_variant), $data);
        if (($gzip = gzencode($data, 9)) !== false) {
            $bytes_written += self::writeFile(self::getGzipFilename($path, $request_variant), $gzip);
        }

        clearstatcache();

        return $bytes_written;
    }


    /**
     * @return string Name of cache method.
     */
    public function getName(): string
    {
        return 'HDD';
    }


    /**
     * @return int Size of cache data.
     */
    public function getSize(): int
    {
        return is_dir(self::CACHE_DIR) ? self::getDirectorySize(self::CACHE_DIR) : 0;
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
}

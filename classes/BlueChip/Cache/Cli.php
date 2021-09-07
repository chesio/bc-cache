<?php

namespace BlueChip\Cache;

/**
 * Delete items from, flush or get size of BC Cache cache
 *
 * @link https://make.wordpress.org/cli/handbook/commands-cookbook/
 */
class Cli
{
    /**
     * @var \BlueChip\Cache\Core
     */
    private $cache;


    /**
     * @param \BlueChip\Cache\Core $cache
     */
    public function __construct(Core $cache)
    {
        $this->cache = $cache;
    }


    /**
     * Delete cache data for particular post or page.
     *
     * ## OPTIONS
     *
     * <post-id>
     * : ID of page or post to delete from cache
     *
     * ## EXAMPLES
     *
     *  wp bc-cache delete 123
     */
    public function delete(array $args, array $assoc_args): void
    {
        if (empty($post_id = \intval($args[0]))) {
            \WP_CLI::error(\sprintf('"%s" is not a valid post ID!', $args[0]));
            return;
        }

        if (empty($url = get_permalink($post_id))) {
            \WP_CLI::error(\sprintf('No URL could be generated for post with ID "%d"', $post_id));
            return;
        }

        $request_variants = Core::getRequestVariants();

        foreach ($request_variants as $request_variant => $request_variant_name) {
            if ($this->cache->delete($url, $request_variant)) {
                \WP_CLI::success(
                    \sprintf('Cache data for post with ID %d and request variant "%s" has been deleted!', $post_id, $request_variant_name)
                );
            } else {
                \WP_CLI::error(
                    \sprintf('Failed to delete cache data for post with ID %d and request variant "%s"!', $post_id, $request_variant_name)
                );
            }
        }
    }


    /**
     * Flush entire cache.
     */
    public function flush(array $args, array $assoc_args): void
    {
        \WP_CLI::line('Flushing BC Cache cache ...');

        if ($this->cache->flush()) {
            \WP_CLI::success('The cache has been flushed!');
        } else {
            \WP_CLI::error('Failed to flush the cache!');
        }
    }


    /**
     * Display total size of all files in cache directory.
     *
     * ## OPTIONS
     *
     * [--human-readable]
     * : Print size information like 1K, 2MB, 3GB etc.
     *
     * @subcommand size
     */
    public function getSize(array $args, array $assoc_args): void
    {
        // Process arguments.
        $human_readable = $assoc_args['human-readable'] ?? false;

        if (\is_int($size_in_bytes = $this->cache->getSize(true))) {
            \WP_CLI::line($human_readable ? size_format($size_in_bytes) : $size_in_bytes);
        } else {
            \WP_CLI::error('Failed to determine cache size!');
        }
    }


    /**
     * Remove cache data for particular URL.
     *
     * ## OPTIONS
     *
     * <url>
     * : URL to remove from cache
     *
     * ## EXAMPLES
     *
     *  wp bc-cache remove http://www.example.com/some/thing/
     */
    public function remove(array $args, array $assoc_args): void
    {
        if (empty($url = \filter_var($args[0], FILTER_VALIDATE_URL))) {
            \WP_CLI::error(\sprintf('"%s" is not a valid URL!', $args[0]));
            return;
        }

        $request_variants = Core::getRequestVariants();

        foreach ($request_variants as $request_variant => $request_variant_name) {
            if ($this->cache->delete($url, $request_variant)) {
                \WP_CLI::success(
                    \sprintf('Cache data for URL "%s" and request variant "%s" has been deleted!', $url, $request_variant_name)
                );
            } else {
                \WP_CLI::error(
                    \sprintf('Failed to delete cache data for URL "%s" and request variant "%s"!', $url, $request_variant_name)
                );
            }
        }
    }
}

<?php
/**
 * @link https://make.wordpress.org/cli/handbook/commands-cookbook/
 * @package BC_Cache
 */

namespace BlueChip\Cache;

/**
 * Delete items from, flush or get size information of BC Cache cache
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
    public function delete(array $args, array $assoc_args)
    {
        if (empty($post_id = intval($args[0]))) {
            \WP_CLI::error(sprintf('"%s" is not a valid post ID!', $args[0]));
            return;
        }

        $url = get_permalink($post_id);

        $request_variants = Core::getRequestVariants();

        foreach ($request_variants as $request_variant) {
            if ($this->cache->delete($url, $request_variant)) {
                \WP_CLI::success(
                    sprintf('Cache data for post with ID %d and request variant "%s" has been deleted!', $post_id, $request_variant)
                );
            } else {
                \WP_CLI::error(
                    sprintf('Failed to delete cache data for post with ID %d and request variant "%s"!', $post_id, $request_variant)
                );
            }
        }
    }


    /**
     * Flush entire cache.
     */
    public function flush(array $args, array $assoc_args)
    {
        \WP_CLI::line('Flushing BC Cache cache ...');

        if ($this->cache->flush()) {
            \WP_CLI::success('The cache has been flushed!');
        } else {
            \WP_CLI::error('Failed to flush the cache!');
        }
    }


    /**
     * Display size information.
     *
     * ## OPTIONS
     *
     * [--human-readable]
     * : Print size information like 1K, 2MB, 3GB etc.
     *
     * @subcommand size
     */
    public function getSize(array $args, array $assoc_args)
    {
        // Process arguments.
        $human_readable = $assoc_args['human-readable'] ?? false;

        $size_in_bytes = $this->cache->getSize(true);

        \WP_CLI::line($human_readable ? size_format($size_in_bytes) : $size_in_bytes);
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
    public function remove(array $args, array $assoc_args)
    {
        if (empty($url = filter_var($args[0], FILTER_VALIDATE_URL))) {
            \WP_CLI::error(sprintf('"%s" is not a valid URL!'), $args[0]);
            return;
        }

        $request_variants = Core::getRequestVariants();

        foreach ($request_variants as $request_variant) {
            if ($this->cache->delete($url, $request_variant)) {
                \WP_CLI::success(
                    sprintf('Cache data for URL "%s" and request variant "%s" has been deleted!', $url, $request_variant)
                );
            } else {
                \WP_CLI::error(
                    sprintf('Failed to delete cache data for URL "%s" and request variant "%s"!', $url, $request_variant)
                );
            }
        }
    }
}

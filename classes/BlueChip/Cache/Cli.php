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
     * @var \BlueChip\Cache\Crawler|null
     */
    private $cache_crawler;

    /**
     * @var \BlueChip\Cache\Feeder|null
     */
    private $cache_feeder;


    /**
     * @param \BlueChip\Cache\Core $cache
     * @param \BlueChip\Cache\Crawler|null $cache_crawler Null value signals that cache warm up is disabled.
     * @param \BlueChip\Cache\Feeder|null $cache_feeder Null value signals that cache warm up is disabled.
     */
    public function __construct(Core $cache, ?Crawler $cache_crawler, ?Feeder $cache_feeder)
    {
        $this->cache = $cache;
        $this->cache_crawler = $cache_crawler;
        $this->cache_feeder = $cache_feeder;
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

        // Use helper method to actually delete related cache entries.
        $this->erase($url, $post_id);
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

        // Use helper method to actually remove related cache entries.
        $this->erase($url);
    }


    /**
     * Erase cache entry data for given $url and re-queue it in case cache warm up is active.
     *
     * @param string $url
     * @param int|null $post_id [optional] If given, mention post ID in error/success message instead of URL.
     */
    private function erase(string $url, ?int $post_id = null): void
    {
        $items_pushed_to_warm_up_queue = 0;

        foreach ($this->cache->getRequestVariants() as $request_variant => $request_variant_name) {
            $cache_item = new Item($url, $request_variant);

            if ($this->cache->delete($cache_item)) {
                if ($this->cache_feeder !== null) {
                    // Push item to feeder, update counter on success.
                    if ($this->cache_feeder->push($cache_item)) {
                        $items_pushed_to_warm_up_queue += 1;
                    }
                }

                \WP_CLI::success(
                    $post_id === null
                    ? \sprintf('Cache data for URL "%s" and request variant "%s" has been deleted!', $url, $request_variant_name)
                    : \sprintf('Cache data for post with ID %d and request variant "%s" has been deleted!', $post_id, $request_variant_name)
                );
            } else {
                \WP_CLI::error(
                    $post_id === null
                    ? \sprintf('Failed to delete cache data for URL "%s" and request variant "%s"!', $url, $request_variant_name)
                    : \sprintf('Failed to delete cache data for post with ID %d and request variant "%s"!', $post_id, $request_variant_name)
                );
            }
        }

        // Activate cache crawler if any items has been pushed to the warm up queue.
        if (($items_pushed_to_warm_up_queue > 0) && ($this->cache_crawler !== null)) {
            $this->cache_crawler->activate();
        }
    }


    /**
     * Warm up cache.
     *
     * @subcommand warm-up
     */
    public function warmUp(array $args, array $assoc_args): void
    {
        if (($this->cache_crawler === null) || ($this->cache_feeder === null)) {
            \WP_CLI::error('Cache warm up is disabled. Exiting ...');
        }

        // Synchronize state of warm up queue with state of cache to get precise warm up queue size.
        if (!$this->cache_feeder->synchronize()) {
            \WP_CLI::error('Synchronizing state of warm up queue with state of cache failed. Exiting ...');
        }

        \WP_CLI::line('Warming up BC Cache cache ...');

        $warm_up_queue_size = $this->cache_feeder->getSize();

        if ($warm_up_queue_size === 0) {
            \WP_CLI::success('Warm up queue is empty.');
            return; // !
        }

        $progress = \WP_CLI\Utils\make_progress_bar('Progress', $warm_up_queue_size);

        while ($this->cache_crawler->step() !== null) {
            $progress->tick();
        }

        $progress->finish();

        \WP_CLI::success('Cache warm up finished.');
    }
}

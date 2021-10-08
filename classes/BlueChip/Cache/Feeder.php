<?php

namespace BlueChip\Cache;

class Feeder
{
    /**
     * @var string
     */
    private const TRANSIENT_CRAWLER_QUEUE = 'bc-cache/transient:crawler-queue';


    /**
     * Fetch next item to crawl.
     *
     * @return array{'url': string, 'request_variant': string}|null Next item to crawl as pair of [`url` and `request_variant`] values or null if queue is empty.
     */
    public function fetch(): ?array
    {
        $queue = get_transient(self::TRANSIENT_CRAWLER_QUEUE);

        if ($queue === []) {
            // No more URLs to crawl.
            return null;
        }

        if (!$queue) {
            // Rebuild queue.
            $queue = $this->requeue();
        }

        $item = \array_shift($queue);

        set_transient(self::TRANSIENT_CRAWLER_QUEUE, $queue);

        return $item ?: null;
    }


    /**
     * @return int|null Count of items in the queue or null if queue has to be rebuilt yet.
     */
    public function getSize(): ?int
    {
        $queue = get_transient(self::TRANSIENT_CRAWLER_QUEUE);

        return \is_array($queue) ? \count($queue) : null;
    }


    /**
     * Reset the queue.
     *
     * @return bool True on success, false on failure.
     */
    public function reset(): bool
    {
        return set_transient(self::TRANSIENT_CRAWLER_QUEUE, null);
    }


    /**
     * Rebuild the queue.
     *
     * @internal The caller must ensure the queue is persisted if necessary.
     *
     * @return array
     */
    private function requeue(): array
    {
        // Get list of URLs to crawl.
        $urls = $this->getUrls();

        // Get request variants to include.
        $request_variants = Core::getRequestVariants();

        $queue = [];

        foreach ($urls as $url) {
            foreach (\array_keys($request_variants) as $request_variant) {
                $queue[] = ['url' => $url, 'request_variant' => $request_variant];
            }
        }

        return $queue;
    }


    /**
     * Get list of URLs to crawl.
     *
     * Internally, this method fetches URLs from core XML sitemap providers, but this handling can be shortcut
     * via `bc-cache/filter:cache-warm-up-initial-url-list` filter.
     *
     * @return string[] List of URLs to crawl
     */
    private function getUrls(): array
    {
        $urls = apply_filters(Hooks::FILTER_CACHE_WARM_UP_INITIAL_URL_LIST, null);

        if ($urls === null) {
            // If no URLs are provided by other means, sse XML sitemaps providers from WordPress core.

            /** @var \WP_Sitemaps_Provider[] $sitemap_providers */
            $sitemap_providers = wp_get_sitemap_providers();

            $url_sets = [];

            foreach ($sitemap_providers as $sitemap_provider) {
                foreach (\array_keys($sitemap_provider->get_object_subtypes()) as $object_subtype) {
                    $max_num_pages = $sitemap_provider->get_max_num_pages($object_subtype);
                    for ($page_num = 1; $page_num <= $max_num_pages; ++$page_num) {
                        $url_sets[] = \array_column(
                            $sitemap_provider->get_url_list($page_num, $object_subtype),
                            'loc'
                        );
                    }
                }
            }

            $urls = \array_merge(...$url_sets);
        }

        // Prepend home URL to the merged list of all URLs as it arguably represents the most important page on website.
        \array_unshift($urls, home_url('/'));

        // Make sure only unique URLs are returned.
        return \array_unique(apply_filters(Hooks::FILTER_CACHE_WARM_UP_FINAL_URL_LIST, $urls));
    }


    /**
     * Reset feeder state on setup.
     *
     * @return bool True on success, false on failure.
     */
    public function setUp(): bool
    {
        return $this->reset();
    }


    /**
     * Remove any persistent information.
     *
     * @return bool True on success, false on failure.
     */
    public function tearDown(): bool
    {
        return delete_transient(self::TRANSIENT_CRAWLER_QUEUE);
    }
}

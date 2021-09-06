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
     * @param array{'url': string, 'request_variant': string} $item Item to crawl as pair of ['url', 'request_variant'] values.
     *
     * @return bool True on success, false on failure.
     */
    public function push(array $item): bool
    {
        $queue = get_transient(self::TRANSIENT_CRAWLER_QUEUE);

        if (\is_array($queue)) {
            $queue[] = $item; // Push at the end of queue.
        } else {
            $queue = $this->requeue(); // Queue has to be rebuilt, so ignore the pushed item.
        }

        return set_transient(self::TRANSIENT_CRAWLER_QUEUE, $queue);
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
     * Internally, this method fetches URLs from XML sitemap providers - the following providers are supported:
     *
     * 1. Yoast SEO - XML sitemaps have to be turned on in plugin settings.
     *
     * If no provider in the list above is available, WordPress core XML sitemap providers are used as fallback method.
     *
     * @return string[] List of URLs to crawl
     */
    private function getUrls(): array
    {
        $urls = [];

        if (isset($GLOBALS['wpseo_sitemaps'])) {
            // XML sitemaps feature in Yoast SEO is active.

            /** @var WPSEO_Sitemap_Provider[] */
            $sitemap_providers = $GLOBALS['wpseo_sitemaps']->providers;

            $entries_per_page = 1000; // This is default value used in Yoast SEO.

            foreach ($sitemap_providers as $sitemap_provider) {
                // Types of sitemaps cannot be retrieved in any simpler way, we have to parse them from index links.
                $index_links = $sitemap_provider->get_index_links($entries_per_page);

                foreach ($index_links as ['loc' => $index_link]) {
                    $matches = [];

                    if (preg_match('/(\w+)\-sitemap(\d*)\.xml$/', $index_link, $matches) !== false) {
                        $type = $matches[1];
                        $page_num = $matches[2] ? (int) $matches[2] : 1;

                        if ($sitemap_provider->handles_type($type)) {
                            $urls[] = \array_column(
                                $sitemap_provider->get_sitemap_links($type, $entries_per_page, $page_num),
                                'loc'
                            );
                        }
                    }
                }
            }
        } else {
            // Use XML sitemaps providers from WordPress core.

            /** @var WP_Sitemaps_Provider[] */
            $sitemap_providers = wp_get_sitemap_providers();

            foreach ($sitemap_providers as $sitemap_provider) {
                foreach (\array_keys($sitemap_provider->get_object_subtypes()) as $object_subtype) {
                    $max_num_pages = $sitemap_provider->get_max_num_pages($object_subtype);
                    for ($page_num = 1; $page_num <= $max_num_pages; ++$page_num) {
                        $urls[] = \array_column(
                            $sitemap_provider->get_url_list($page_num, $object_subtype),
                            'loc'
                        );
                    }
                }
            }
        }

        // Prepend home URL to the merged list of all URLs as it arguably represents the most important page on website.
        return \array_unique(apply_filters(Hooks::FILTER_CACHE_WARM_UP_URL_LIST, \array_merge([home_url('/'),], ...$urls)));
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

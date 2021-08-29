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
     * @return array|null Next item to crawl as pair ['url', 'request_variant'] or null if queue is empty.
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
     * @return bool
     */
    public function reset(): bool
    {
        return set_transient(self::TRANSIENT_CRAWLER_QUEUE, null);
    }


    /**
     * Rebuild the queue and return it.
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
            foreach ($request_variants as $request_variant) {
                $queue[] = ['url' => $url, 'request_variant' => $request_variant];
            }
        }

        set_transient(self::TRANSIENT_CRAWLER_QUEUE, $queue);

        return $queue;
    }


    /**
     * Get list of URLs to crawl using WordPress core sitemap providers.
     *
     * @return array List of URLs to crawl
     */
    public function getUrls(): array
    {
        // Put home URL at the top of the list.
        $urls = [
            home_url('/'),
        ];

        $sitemap_providers = wp_get_sitemap_providers();

        foreach ($sitemap_providers as $sitemap_provider) {
            foreach (\array_keys($sitemap_provider->get_object_subtypes()) as $object_subtype) {
                $max_num_pages = $sitemap_provider->get_max_num_pages($object_subtype);
                for ($page_num = 1; $page_num <= $max_num_pages; ++$page_num) {
                    $urls = \array_merge(
                        $urls,
                        \array_column($sitemap_provider->get_url_list($page_num, $object_subtype), 'loc')
                    );
                }
            }
        }

        return \array_unique(apply_filters(Hooks::FILTER_CACHE_WARM_UP_URL_LIST, $urls));
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
     * @return bool
     */
    public function tearDown(): bool
    {
        return delete_transient(self::TRANSIENT_CRAWLER_QUEUE);
    }
}

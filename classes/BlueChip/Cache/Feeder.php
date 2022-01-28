<?php

namespace BlueChip\Cache;

/**
 * Feeder acts as proxy to actual warm up queue object, but provides two additional functions as well:
 * - it automatically populates the warm up queue when necessary
 * - it takes care of persistence and stores warm up queue as transient
 */
class Feeder
{
    /**
     * @var string
     */
    private const TRANSIENT_CRAWLER_QUEUE = 'bc-cache/transient:crawler-queue';


    /**
     * Fetch next item to crawl.
     *
     * @return Item|null Next item to crawl or null if queue is empty.
     */
    public function fetch(): ?Item
    {
        $queue = $this->getQueue(true);

        if ($queue->isEmpty()) {
            // No more URLs to crawl.
            return null;
        }

        $item = $queue->fetch();

        $this->setQueue($queue);

        return $item;
    }


    /**
     * @param Item $item Item to uncrawl.
     *
     * @return bool True on success, false on failure.
     */
    public function pull(Item $item): bool
    {
        $queue = $this->getQueue(true);

        $queue->pull($item);

        return $this->setQueue($queue);
    }


    /**
     * @param Item $item Item to crawl.
     *
     * @return bool True on success, false on failure.
     */
    public function push(Item $item): bool
    {
        $queue = $this->getQueue(true);

        $queue->push($item);

        return $this->setQueue($queue);
    }


    /**
     * @param bool $strict [optional] If true, queue will be rebuilt on demand.
     *
     * @return int|null Count of items waiting in the queue or null if queue has to be rebuilt yet and $strict was false.
     */
    public function getSize(bool $strict = false): ?int
    {
        $queue = $this->getQueue($strict);

        return $queue ? $queue->getWaitingCount() : null;
    }


    /**
     * @return array
     */
    public function getStats(): array
    {
        return $this->getQueue(true)->getStats();
    }


    /**
     * Reset the queue.
     *
     * @return bool True on success, false on failure.
     */
    public function reset(): bool
    {
        return $this->setQueue(null);
    }


    private function getQueue(bool $rebuild = false): ?WarmUpQueue
    {
        /** @var WarmUpQueue|null $queue */
        $queue = get_transient(self::TRANSIENT_CRAWLER_QUEUE) ?: null;

        if (!($queue instanceof WarmUpQueue) && $rebuild) {
            // Rebuild queue.
            $queue = new WarmUpQueue($this->getItems());
            // And save.
            $this->setQueue($queue);
        }

        return $queue;
    }


    private function setQueue(?WarmUpQueue $queue): bool
    {
        return set_transient(self::TRANSIENT_CRAWLER_QUEUE, $queue);
    }


    /**
     * Rebuild the list of warm up queue items.
     *
     * @internal The caller must ensure the queue is persisted if necessary.
     *
     * @return Item[]
     */
    private function getItems(): array
    {
        // Get list of URLs to crawl.
        $urls = $this->getUrls();

        // Get request variants to include.
        $request_variants = Core::getRequestVariants();

        $items = [];

        foreach ($urls as $url) {
            foreach (\array_keys($request_variants) as $request_variant) {
                $items[] = new Item($url, $request_variant);
            }
        }

        return $items;
    }


    /**
     * Get list of URLs to crawl.
     *
     * @return string[] List of URLs to crawl
     */
    private function getUrls(): array
    {
        // Following filter allows to shortcut reading of XML sitemaps.
        $urls = apply_filters(Hooks::FILTER_CACHE_WARM_UP_INITIAL_URL_LIST, null);

        if ($urls === null) {
            // Initialize XML sitemap reader.
            $xml_sitemap_reader = new XmlSitemapReader(home_url('robots.txt'), home_url('sitemap.xml'));

            try {
                $urls = $xml_sitemap_reader->getUrls();
            } catch (Exception $e) {
                // Trigger a warning and let WordPress handle it.
                \trigger_error($e, E_USER_WARNING);
                // Sorry, no URLs available.
                $urls = [];
            }
        }

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

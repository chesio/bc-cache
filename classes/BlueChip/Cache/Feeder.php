<?php

namespace BlueChip\Cache;

/**
 * Feeder acts as proxy to actual warm up queue object, but provides two additional functions as well:
 * - it automatically populates the warm up queue when necessary
 * - it takes care of persistence and stores warm up queue as transient
 *
 * @internal All public methods employ locking in order to ensure atomicity of operations on the (global) queue object.
 */
class Feeder
{
    /**
     * @var string
     */
    private const TRANSIENT_CRAWLER_QUEUE = 'bc-cache/transient:crawler-queue';

    /**
     * @var \BlueChip\Cache\Core
     */
    private $cache;

    /**
     * @var Lock
     */
    private $lock;


    /**
     * @param \BlueChip\Cache\Core $cache
     * @param Lock $lock Lock to use to ensure atomicity of operations.
     */
    public function __construct(Core $cache, Lock $lock)
    {
        $this->cache = $cache;
        $this->lock = $lock;
    }


    /**
     * Fetch next item to crawl.
     *
     * @return Item|null Next item to crawl or null if there are no more uncached items in queue or there has been an error.
     */
    public function fetch(): ?Item
    {
        // Get an exclusive lock.
        if (!$this->lock->acquire(true)) {
            // If lock cannot be acquired, bail.
            return null;
        }

        $queue = $this->getQueue();

        if ($queue->isEmpty()) {
            $this->lock->release(); // !
            return null;
        }

        // Keep fetching items untile there are no items or we found item that has not been cached yet.
        while ((($item = $queue->fetch()) !== null) && $this->cache->has($item));

        // Fetch has changed queue state, save it.
        $this->setQueue($queue);

        $this->lock->release(); // !

        return $item;
    }


    /**
     * @param Item $item Item to mark as waiting.
     *
     * @return bool True on success, false on failure.
     */
    public function push(Item $item): bool
    {
        // Get an exclusive lock.
        if (!$this->lock->acquire(true)) {
            // If lock cannot be acquired, fail.
            return false;
        }

        $queue = $this->getQueue();

        $status = $queue->push($item) ? $this->setQueue($queue) : true;

        $this->lock->release(); // !

        return $status;
    }


    /**
     * @return int Count of items waiting in the queue.
     */
    public function getSize(): int
    {
        // Get shared lock, but continue even if it could not be acquired.
        $locked = $this->lock->acquire(false);

        $queue = $this->getQueue();

        $count = $queue->getWaitingCount();

        if ($locked) {
            $this->lock->release(); // !
        }

        return $count;
    }


    /**
     * @return array Warm up queue statistics as: {'processed' => int, 'waiting' => int, 'total' => int}
     */
    public function getStats(): array
    {
        // Get shared lock, but continue even if it could not be acquired.
        $locked = $this->lock->acquire(false);

        $stats = $this->getQueue()->getStats();

        if ($locked) {
            $this->lock->release(); // !
        }

        return $stats;
    }


    /**
     * Reset the queue.
     *
     * @return bool True on success, false on failure.
     */
    public function reset(): bool
    {
        // Get an exclusive lock.
        if (!$this->lock->acquire(true)) {
            // If lock cannot be acquired, fail.
            return false;
        }

        $status = $this->setQueue(null);

        $this->lock->release(); // !

        return $status;
    }


    private function getQueue(): WarmUpQueue
    {
        /** @var WarmUpQueue|null $queue */
        $queue = get_transient(self::TRANSIENT_CRAWLER_QUEUE) ?: null;

        if (!($queue instanceof WarmUpQueue)) {
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
        $request_variants = $this->cache->getRequestVariants();

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

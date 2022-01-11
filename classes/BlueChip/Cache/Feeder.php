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
     * @return string[] List of URLs to crawl
     */
    private function getUrls(): array
    {
        // Following filter allows to shortcut reading of XML sitemaps.
        $urls = apply_filters(Hooks::FILTER_CACHE_WARM_UP_INITIAL_URL_LIST, null);

        if ($urls === null) {
            try {
                $urls = XmlSitemapReader::getUrls();
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

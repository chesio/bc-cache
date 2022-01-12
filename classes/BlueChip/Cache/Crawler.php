<?php

namespace BlueChip\Cache;

class Crawler
{
    /**
     * @var string Name of action registered as cron job hook.
     */
    private const CRON_JOB_HOOK = 'bc-cache/run-warm-up-crawler';

    /**
     * @var array Default arguments for HTTP request made as part of cache warm up.
     */
    private const DEFAULT_WARM_UP_REQUEST_ARGS = [
        'redirection' => 0, // Do not follow any redirects, they are not going to get cached anyway.
    ];

    /**
     * @var int Default warm up invocation delay (= time between last cache flush and warm up start).
     */
    private const DEFAULT_CACHE_WARM_UP_INVOCATION_DELAY = 10 * MINUTE_IN_SECONDS;


    /**
     * @var Core
     */
    private $cache;

    /**
     * @var Feeder
     */
    private $cache_feeder;


    /**
     * @param \BlueChip\Cache\Core $cache
     * @param \BlueChip\Cache\Feeder $cache_feeder
     */
    public function __construct(Core $cache, Feeder $cache_feeder)
    {
        $this->cache = $cache;
        $this->cache_feeder = $cache_feeder;
    }


    /**
     * @return bool True if crawler run has been successfully scheduled, false otherwise.
     */
    public function activate(): bool
    {
        // Unschedule any scheduled event first.
        if (($timestamp = wp_next_scheduled(self::CRON_JOB_HOOK)) !== false) {
            wp_unschedule_event($timestamp, self::CRON_JOB_HOOK);
        }

        // By default, crawling starts with a delay, but this can be filtered.
        $delay = apply_filters(Hooks::FILTER_CACHE_WARM_UP_INVOCATION_DELAY, self::DEFAULT_CACHE_WARM_UP_INVOCATION_DELAY);

        return $this->schedule(\time() + $delay);
    }


    /**
     * @return bool True if crawler has been successfully unscheduled, false otherwise.
     */
    public function deactivate(): bool
    {
        // Unregister hook for warm up crawler.
        return \is_int(wp_unschedule_hook(self::CRON_JOB_HOOK));
    }


    /**
     * Schedule next crawler run.
     *
     * @internal No checks are done whether any crawler run is already scheduled, but WordPress does some checks itself.
     *
     * @link https://developer.wordpress.org/reference/functions/wp_schedule_single_event/
     *
     * @param int|null $timestamp [optional] Unix timestamp of next crawler run - if not provided, current time is used.
     *
     * @return bool True if crawler run has been successfully scheduled, false otherwise.
     */
    public function schedule(?int $timestamp = null): bool
    {
        return wp_schedule_single_event($timestamp ?: \time(), self::CRON_JOB_HOOK);
    }


    /**
     * Initialize crawler by hooking it to action registered to WP-Cron.
     */
    public function init(): void
    {
        add_action(self::CRON_JOB_HOOK, [$this, 'runCronJob'], 10, 0);
    }


    /**
     * Run cache warm up.
     *
     * @internal This method is hooked to WP-Cron.
     */
    public function runCronJob(): void
    {
        // A single warm up run must not run (much) longer than single WP-Cron run is allowed/expected to take, therefore:
        $now = \microtime(true);
        // 1) Get the time current WP-Cron invocation started. If unknown, assume it just started now.
        $wp_cron_start_time = get_transient('doing_cron') ?: $now;
        // 2) Get the time-out value: it is equal to WP-Cron time-out by default, but can be set to a *smaller* value.
        $timeout = \min(apply_filters(Hooks::FILTER_CACHE_WARM_UP_RUN_TIMEOUT, WP_CRON_LOCK_TIMEOUT), WP_CRON_LOCK_TIMEOUT);
        // 3) Compute time-out value adjusted to time left in current WP-Cron invocation.
        $adjusted_timeout = (int) max($wp_cron_start_time + $timeout - $now, 0);

        // Run the warm up with computed timeout...
        if ($this->run($adjusted_timeout) !== 0) {
            // ...if there are any items to crawl left, schedule next WP-Cron invocation.
            $this->schedule();
        }
    }


    /**
     * Run cache warm up.
     *
     * Note: warm up run stops immediately if any HTTP request fails or gets a server error response (HTTP status code 5xx).
     *
     * @param int|null $timeout [optional] Run at maximum given number of seconds (0 secs = stop after single HTTP request).
     *
     * @return int Number of items left in warm up queue after run.
     */
    public function run(?int $timeout = null): int
    {
        if ($timeout === null) {
            // Run as long as HTTP requests do not fail.
            while ($this->step());
        } else {
            // If timeout is given, set stop time mark.
            $stop_at = (\microtime(true) + $timeout);
            // Run as long as HTTP requests do not fail and allowed time do not run out.
            while ($this->step() && (\microtime(true) < $stop_at));
        }

        return $this->cache_feeder->getSize();
    }


    /**
     * Fetch next item from warm up queue and crawl it.
     *
     * @return bool|null True if remote HTTP request succeeded, false if it failed, null when there are no more items to crawl.
     */
    public function step(): ?bool
    {
        // Get next item to crawl.
        if (($item = $this->cache_feeder->fetch()) !== null) {
            // Get URL and request variant to crawl.
            ['url' => $url, 'request_variant' => $request_variant] = $item;

            // If item has been cached yet...
            if ($this->cache->has($url, $request_variant)) {
                return true;
            }

            // Get warm up HTTP request arguments.
            $args = apply_filters(Hooks::FILTER_CACHE_WARM_UP_REQUEST_ARGS, self::DEFAULT_WARM_UP_REQUEST_ARGS, $url, $request_variant);

            // Get the URL...
            $response = wp_remote_get($url, $args);

            // ...fetch response code...
            $response_code = wp_remote_retrieve_response_code($response);

            // ...and signal success if there was no server error.
            return ($response_code !== '') && ($response_code < 500);
        }

        // There are no more items in warm up queue.
        return null;
    }
}

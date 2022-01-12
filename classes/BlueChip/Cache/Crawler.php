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
        add_action(self::CRON_JOB_HOOK, [$this, 'run'], 10, 0);
    }


    /**
     * Run cache warm up.
     *
     * @internal This method is hooked to WP-Cron.
     */
    public function run(): void
    {
        // Make sure we don't run (much) longer that single WP-Cron run is allowed/expected to take.
        $wp_cron_start_time = get_transient('doing_cron') ?: \microtime(true);
        // Warm up time to run value can be filtered...
        $timeout = apply_filters(Hooks::FILTER_CACHE_WARM_UP_RUN_TIMEOUT, WP_CRON_LOCK_TIMEOUT);
        // ...but it can only be set to value equal or lower than (default) WP_CRON_LOCK_TIMEOUT.
        $stop_at = (float) ($wp_cron_start_time + \min($timeout, WP_CRON_LOCK_TIMEOUT));

        // Get URLs to crawl (including request variants).
        while (($item = $this->cache_feeder->fetch()) !== null) {
            // Get URL and request variant to crawl.
            ['url' => $url, 'request_variant' => $request_variant] = $item;

            // If item is not cached yet...
            if (!$this->cache->has($url, $request_variant)) {
                // Get warm up HTTP request arguments.
                $args = apply_filters(Hooks::FILTER_CACHE_WARM_UP_REQUEST_ARGS, self::DEFAULT_WARM_UP_REQUEST_ARGS, $url, $request_variant);

                // Get the URL...
                $response = wp_remote_get($url, $args);

                if (is_wp_error($response)) {
                    // Bail current WP-Cron invocation if there has been an error.
                    break;
                }

                $response_code = wp_remote_retrieve_response_code($response);

                if (!\is_int($response_code) || !($response_code < 500)) {
                    // Bail current WP-Cron invocation in case of invalid response or if server is experiencing issues.
                    break;
                }
            }

            if (\microtime(true) >= $stop_at) {
                // Stop if we run out of time in current WP-Cron invocation.
                break;
            }
        }

        // If there are any items to crawl left, schedule next crawl.
        if (($this->cache_feeder->getSize() ?: 0) > 0) {
            $this->schedule();
        }
    }
}

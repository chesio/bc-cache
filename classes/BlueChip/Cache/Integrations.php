<?php

declare(strict_types=1);

namespace BlueChip\Cache;

/**
 * Container for integrations with 3rd party plugins.
 */
abstract class Integrations
{
    public static function initialize(): void
    {
        // Integration with Autoptimize
        if (\defined('AUTOPTIMIZE_PLUGIN_VERSION')) {
            // Flush our caches when Autoptimize purges its caches.
            add_filter(Hooks::FILTER_FLUSH_HOOKS, [self::class, 'flushOnAutoptimizePurge'], 10, 1);
            // Do not warn about missing page caching plugin ;-)
            add_filter('autoptimize_filter_main_show_pagecache_notice', '__return_false', 10, 0);
        }
    }


    /**
     * Add Autoptimize purge action to list of actions that trigger cache flush.
     *
     * @param array<string,int> $hooks
     *
     * @return array<string,int>
     */
    public static function flushOnAutoptimizePurge(array $hooks): array
    {
        $hooks['autoptimize_action_cachepurged'] = 100;

        return $hooks;
    }
}

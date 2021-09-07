<?php

namespace BlueChip\Cache;

/**
 * Container for integrations with 3rd party plugins.
 */
abstract class Integrations
{
    public static function initialize()
    {
        // Integration with Autoptimize
        if (defined('AUTOPTIMIZE_PLUGIN_VERSION')) {
            add_filter(Hooks::FILTER_FLUSH_HOOKS, [self::class, 'flushOnAutoptimizePurge'], 10, 1);
        }

        // Integration with Yoast SEO
        if (isset($GLOBALS['wpseo_sitemaps'])) {
            // XML sitemaps feature in Yoast SEO is active.
            add_filter(Hooks::FILTER_CACHE_WARM_UP_INITIAL_URL_LIST, [self::class, 'getUrlsFromYoastSeoSitemap'], 10, 0);
        }
    }


    /**
     * Add Autoptimize purge action to list of actions that trigger cache flush.
     *
     * @param array $hooks
     *
     * @return array
     */
    public static function flushOnAutoptimizePurge(array $hooks): array
    {
        $hooks['autoptimize_action_cachepurged'] = 100;

        return $hooks;
    }


    /**
     * @return string[]|null List of URLs from Yoast SEO sitemap or null if the sitemap is not activated.
     */
    public static function getUrlsFromYoastSeoSitemap(): ?array
    {
        if (!isset($GLOBALS['wpseo_sitemaps'])) {
            // This sanity check is perhaps unnecessary, but this method is public after all.
            return null;
        }

        $sitemap_providers = $GLOBALS['wpseo_sitemaps']->providers;

        $entries_per_page = 1000; // This is default value used in Yoast SEO.

        $url_sets = [];

        foreach ($sitemap_providers as $sitemap_provider) {
            // Types of sitemaps cannot be retrieved in any simpler way, we have to parse them from index links.
            $index_links = $sitemap_provider->get_index_links($entries_per_page);

            foreach ($index_links as ['loc' => $index_link]) {
                $matches = [];

                if (preg_match('/(\w+)\-sitemap(\d*)\.xml$/', $index_link, $matches) !== false) {
                    $type = $matches[1];
                    $page_num = $matches[2] ? (int) $matches[2] : 1;

                    if ($sitemap_provider->handles_type($type)) {
                        $url_sets[] = \array_column(
                            $sitemap_provider->get_sitemap_links($type, $entries_per_page, $page_num),
                            'loc'
                        );
                    }
                }
            }
        }

        return \array_merge(...$url_sets);
    }
}

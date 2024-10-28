<?php

declare(strict_types=1);

namespace BlueChip\Cache;

class AdminBar
{
    /**
     * @var string Name of nonce used for AJAX-ified flush cache requests.
     */
    private const NONCE_FLUSH_CACHE_REQUEST = 'bc-cache/nonce:flush-cache-request';


    public function __construct(
        private string $plugin_filename,
        private Core $cache,
        private ?Feeder $cache_feeder,
        private ?Crawler $cache_crawler,
        private Viewer $cache_viewer,
    ) {
    }


    public function init(): void
    {
        // Register method handling AJAX call from admin bar.
        add_action('wp_ajax_bc_cache_flush_cache', $this->processFlushRequest(...), 10, 0);

        if (is_admin_bar_showing()) {
            // Add items to admin bar.
            add_action('admin_bar_init', $this->enqueueToolbarAssets(...), 10, 0);
            add_action('admin_bar_menu', $this->addToolbar(...), 110, 1);
        }
    }


    /**
     * Process AJAX flush request.
     *
     * @internal Is executed in context of AJAX request.
     */
    private function processFlushRequest(): void
    {
        // Check AJAX referer - die if invalid.
        check_ajax_referer(self::NONCE_FLUSH_CACHE_REQUEST, false, true);

        // TODO: in case of failure, indicate whether it's been access rights or I/O error.
        if (Utils::canUserFlushCache() && $this->cache->flush()) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/admin_bar_init/
     */
    private function enqueueToolbarAssets(): void
    {
        wp_enqueue_style(
            'bc-cache-toolbar',
            plugins_url('assets/toolbar.css', $this->plugin_filename),
            [],
            '20181201',
            'all'
        );

        if (Utils::canUserFlushCache()) {
            wp_enqueue_script(
                'bc-cache-toolbar',
                plugins_url('assets/toolbar.js', $this->plugin_filename),
                ['jquery'],
                '20190731',
                true
            );

            wp_localize_script(
                'bc-cache-toolbar',
                'bc_cache_ajax_object',
                [
                    'ajaxurl' => admin_url('admin-ajax.php'), // necessary for the AJAX work properly on the frontend
                    'nonce' => wp_create_nonce(self::NONCE_FLUSH_CACHE_REQUEST),
                    'empty_cache_text' => __('Empty cache', 'bc-cache'),
                    'warm_up_reset_text' => __('Warm-up status unavailable.', 'bc-cache'),
                    'zero_age_text' => human_time_diff(\time()),
                    'zero_size_text' => size_format(0),
                ]
            );
        }
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/admin_bar_menu/
     *
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    private function addToolbar(\WP_Admin_Bar $wp_admin_bar): void
    {
        // If warm-up is enabled, add info about warm-up progress/status to the toolbar as well.
        if ($this->cache_crawler && $this->cache_feeder) {
            switch ($this->cache_crawler->getState()) {
                case CrawlingState::FINISHED:
                    $warm_up_class = 'bc-cache-warm-up-finished';
                    $warm_up_status = __('Website is fully cached.', 'bc-cache');
                    break;
                case CrawlingState::RUNNING:
                    $warm_up_class = 'bc-cache-warm-up-runs';
                    $warm_up_status = \sprintf(
                        __('Warm-up in progress (%d%%)', 'bc-cache'),
                        $this->cache_feeder->getProgress(),
                    );
                    break;
                case CrawlingState::SCHEDULED:
                    $warm_up_class = 'bc-cache-warm-up-runs';
                    $warm_up_status = \sprintf(
                        __('Warm-up starts in %s.', 'bc-cache'),
                        // Note: fallback value is not necessary here, but makes PHPStan happy.
                        human_time_diff($this->cache_crawler->getNextScheduled() ?? 0),
                    );
                    break;
                case CrawlingState::STALLED:
                    $warm_up_class = 'bc-cache-warm-up-stalled';
                    $warm_up_status = \sprintf(
                        __('Warm-up stalled at %d%%.', 'bc-cache'),
                        $this->cache_feeder->getProgress(),
                    );
                    break;
            }
        } else {
            $warm_up_class = 'bc-cache-warm-up-off';
            $warm_up_status = '';
        }

        // Add main BC Cache node.
        $wp_admin_bar->add_node([
            'id'    => 'bc-cache',
            // 'parent' => 'top-secondary',
            'title' => '<span class="ab-icon dashicons-html"></span><span class="ab-label">' . esc_html__('BC Cache', 'bc-cache') . '</span>',
            'href'  => current_user_can(Viewer::REQUIRED_CAPABILITY) ? $this->cache_viewer->getUrl() : '',
            'meta'  => [
                'class' => $warm_up_class,
            ]
        ]);

        // Add "stats" node.
        $wp_admin_bar->add_node([
            'id'        => 'bc-cache-stats',
            'title'     => $this->getStatsNodeTable($this->cache->getSize(), $this->cache->getAge(), $warm_up_status),
            'parent'    => 'bc-cache',
        ]);

        // Add "clear cache" node, but only if current user can flush cache.
        if (Utils::canUserFlushCache()) {
            $wp_admin_bar->add_node([
                'id'        => 'bc-cache-clear-button',
                'title'     => '<button id="bc-cache-clear"><span class="ab-icon dashicons-trash"><span class="bc-cache-spinner"></span></span><span class="ab-label">' . esc_html__('Clear cache', 'bc-cache') . '</span></button>',
                'parent'    => 'bc-cache',
            ]);
        }
    }


    private function getStatsNodeTable(?int $cache_size, ?int $cache_age, string $warm_up_status): string
    {
        $rows = [];

        // Heading
        $rows[] = '<tr><th colspan="2">' . esc_html__('BC Cache Info', 'bc-cache') . '</th></tr>';

        if ($warm_up_status) {
            // Warm-up status (optional)
            $rows[] = '<tr><td colspan="2" class="bc-cache-warm-up-status" id="bc-cache-toolbar-warm-up-status">' . esc_html($warm_up_status) . '</td></tr>';
        }

        // Cache size
        $rows[] =  '<tr><td>' . esc_html__('Size:', 'bc-cache') . '</td><td id="bc-cache-toolbar-cache-size">' . esc_html(\is_int($cache_size) ? (string)size_format($cache_size) : __('unknown', 'bc-cache')) . '</td></tr>';
        // Cache age
        $rows[] = '<tr><td>' . esc_html__('Age:', 'bc-cache') . '</td><td id="bc-cache-toolbar-cache-age">' . esc_html(\is_int($cache_age) ? human_time_diff($cache_age) : __('unknown', 'bc-cache')) . '</td></tr>';

        return '<table>' . \implode('', $rows) . '</table>';
    }
}

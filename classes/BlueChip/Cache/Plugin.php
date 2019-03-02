<?php
/**
 * @package BC_Cache
 */

namespace BlueChip\Cache;

class Plugin
{
    /**
     * @var string Path to root cache directory
     */
    const CACHE_DIR = WP_CONTENT_DIR . '/cache/bc-cache';

    /**
     * @var string URL of root cache directory
     */
    const CACHE_URL = WP_CONTENT_URL . '/cache/bc-cache';

    /**
     * Name of nonce used for AJAX-ified flush cache requests.
     */
    const NONCE_FLUSH_CACHE_REQUEST = 'bc-cache/nonce:flush-cache-request';

    /**
     * @var string Name of transient used to cache cache size.
     */
    const TRANSIENT_CACHE_SIZE = 'bc-cache/transient:cache-size';

    /**
     * List of default actions that trigger cache flushing including priority with which the flush method is hooked.
     */
    const FLUSH_CACHE_HOOKS = [
        // Core code changes
        '_core_updated_successfully' => 10,
        // Front-end layout changes
        'switch_theme' => 10,
        'wp_update_nav_menu' => 10,
        // Post content changes
        'save_post' => 20,
        'edit_post' => 20,
        'delete_post' => 20,
        'wp_trash_post' => 20,
        // Comment content changes
        'comment_post' => 10,
        'edit_comment' => 10,
        'delete_comment' => 10,
        'wp_set_comment_status' => 10,
    ];


    /**
     * @var string
     */
    private $plugin_filename;

    /**
     * @var \BlueChip\Cache\Core
     */
    private $cache;


    /**
     * Perform activation and installation tasks.
     *
     * @internal Method should be run on plugin activation.
     * @link https://developer.wordpress.org/plugins/the-basics/activation-deactivation-hooks/
     */
    public function activate()
    {
        $this->cache->flush();
    }


    /**
     * Perform deactivation tasks.
     *
     * @internal Method should be run on plugin deactivation.
     * @link https://developer.wordpress.org/plugins/the-basics/activation-deactivation-hooks/
     */
    public function deactivate()
    {
        $this->cache->flush();
    }


    /**
     * Perform uninstallation tasks.
     *
     * @internal Method should be run on plugin uninstall.
     * @link https://developer.wordpress.org/plugins/the-basics/uninstall-methods/
     */
    public function uninstall()
    {
        $this->cache->flush(true);
    }


    /**
     * @param string $plugin_filename
     */
    public function __construct(string $plugin_filename)
    {
        $this->plugin_filename = $plugin_filename;
        $this->cache = new Core(self::CACHE_DIR, self::TRANSIENT_CACHE_SIZE);
    }


    /**
     * Load the plugin by hooking into WordPress actions and filters.
     *
     * @internal Method should be invoked immediately on plugin load.
     */
    public function load()
    {
        // Register initialization method.
        add_action('init', [$this, 'init'], 10, 0);

        // Register method handling AJAX call from admin bar icon (or elsewhere).
        add_action('wp_ajax_bc_cache_flush_cache', [$this, 'processFlushRequest'], 10, 0);

        // Integrate with WP-CLI.
        add_action('cli_init', function () {
            \WP_CLI::add_command('bc-cache', new Cli($this->cache));
        });
    }


    /**
     * Perform initialization tasks.
     *
     * @action https://developer.wordpress.org/reference/hooks/init/
     */
    public function init()
    {
        // Add Disallow section to robots.txt.
        add_filter('robots_txt', [$this, 'alterRobotsTxt'], 10, 1);

        // Add actions to flush entire cache.
        foreach (apply_filters(Hooks::FILTER_FLUSH_HOOKS, self::FLUSH_CACHE_HOOKS) as $hook => $priority) {
            add_action($hook, [$this, 'flushCacheOnce'], $priority, 0);
        }

        // Add action to flush entire cache manually with do_action().
        add_action(Hooks::ACTION_FLUSH_CACHE, [$this, 'flushCacheOnce'], 10, 0);

        // Add flush icon to admin bar.
        if (is_admin_bar_showing() && self::canUserFlushCache()) {
            add_action('admin_bar_init', [$this, 'enqueueFlushIconAssets'], 10, 0);
            add_action('admin_bar_menu', [$this, 'addFlushIcon'], 90, 1);
        }

        if (is_admin()) {
            // Initialize cache viewer.
            (new Viewer($this->cache))->init();

            if (self::canUserFlushCache()) {
                add_filter('dashboard_glance_items', [$this, 'addDashboardInfo'], 10, 1);
                add_action('rightnow_end', [$this, 'enqueueDashboardAssets'], 10, 0);
            }
        } else {
            // Add action to catch output buffer.
            add_action('template_redirect', [$this, 'startOutputBuffering'], 0, 0);
        }
    }


    /**
     * @filter https://developer.wordpress.org/reference/hooks/robots_txt/
     *
     * @param string $data
     * @return string
     */
    public function alterRobotsTxt(string $data): string
    {
        // Get path component of cache directory URL.
        $path = wp_parse_url(self::CACHE_URL, PHP_URL_PATH);
        // Disallow direct access to cache directory.
        return $data . PHP_EOL . sprintf('Disallow: %s/', $path) . PHP_EOL;
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/admin_bar_init/
     */
    public function enqueueFlushIconAssets()
    {
        wp_enqueue_style(
            'bc-cache-toolbar',
            plugins_url('assets/toolbar.css', $this->plugin_filename),
            [],
            '20181201',
            'all'
        );

        wp_enqueue_script(
            'bc-cache-toolbar',
            plugins_url('assets/toolbar.js', $this->plugin_filename),
            ['jquery'],
            '20181201',
            true
        );

        wp_localize_script(
            'bc-cache-toolbar',
            'bc_cache_ajax_object',
            [
                'ajaxurl' => admin_url('admin-ajax.php'), // necessary for the AJAX work properly on the frontend
                'nonce' => wp_create_nonce(self::NONCE_FLUSH_CACHE_REQUEST),
                'empty_cache_text' => __('Empty cache', 'bc-cache'),
            ]
        );
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/admin_bar_menu/
     *
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function addFlushIcon(\WP_Admin_Bar $wp_admin_bar)
    {
        $wp_admin_bar->add_node([
            'id'     => 'bc-cache',
            'parent' => 'top-secondary',
            'title'  => '<span class="ab-icon dashicons"></span><span class="bc-cache-spinner"></span>',
            'meta'   => [
                'title' => __('Flush the cache', 'bc-cache'),
            ],
        ]);
    }


    /**
     * Add info about cache size to "At a Glance" box on dashboard. The snippet is linked to cache viewer page.
     *
     * @filter https://developer.wordpress.org/reference/hooks/dashboard_glance_items/
     *
     * @param array $items
     * @return array
     */
    public function addDashboardInfo(array $items): array
    {
        $size = $this->cache->getSize();

        $icon = sprintf(
            '<svg style="width: 20px; height: 20px; fill: #82878c; float: left; margin-right: 5px;" aria-hidden="true" role="img"><use xlink:href="%s#bc-cache-icon-hdd"></svg>',
            plugins_url('assets/icons.svg', $this->plugin_filename)
        );

        $cache_size = empty($size)
            ? __('Empty cache', 'bc-cache')
            : sprintf(__('%s cache', 'bc-cache'), size_format($size))
        ;

        // Label has ID, so we can target (update) it via JavaScript.
        $label = '<span id="bc-cache-size">' . esc_html($cache_size) . '</span>';

        // Wrap icon and label in a link to cache viewer.
        $items[] = '<a class="bc-cache-size" href="' . Viewer::getUrl() . '">' . $icon . ' ' . $label . '</a>';

        return $items;
    }


    /**
     * Print short HTML snippet with CSS rules for cache size information in "At a Glance" box.
     *
     * @action https://developer.wordpress.org/reference/hooks/admin_print_footer_scripts/
     */
    public function printDashboardStyles()
    {
        echo '<style>#dashboard_right_now li .bc-cache-size:before { content: ""; display: none; }</style>';
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/rightnow_end/
     */
    public function enqueueDashboardAssets()
    {
        // Print the styles in the footer.
        add_action('admin_print_footer_scripts', [$this, 'printDashboardStyles'], 10, 0);
    }


    /**
     * Flush cache once per request only.
     *
     * @see Core::flush()
     * @return Cached result of call to Core::flush().
     */
    public function flushCacheOnce(): bool
    {
        static $is_flushed = null;

        if (is_null($is_flushed)) {
            $is_flushed = $this->cache->flush();
        }

        return $is_flushed;
    }


    /**
     * Process AJAX flush request.
     *
     * @internal Should be executed in context of AJAX request only.
     */
    public function processFlushRequest()
    {
        // Check AJAX referer - die, if invalid.
        check_ajax_referer(self::NONCE_FLUSH_CACHE_REQUEST, false, true);

        // TODO: in case of failure, indicate whether it's been access rights or I/O error.
        if (self::canUserFlushCache() && $this->cache->flush()) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }


    /**
     * Start caching of output, but only if current page should be cached.
     *
     * @action https://developer.wordpress.org/reference/hooks/template_redirect/
     */
    public function startOutputBuffering()
    {
        if (!self::skipCache()) {
            ob_start([$this, 'handleOutputBuffer']);
        }
    }


    /**
     * Push $buffer to cache and return it on output.
     *
     * @param string $buffer
     * @return string
     */
    public function handleOutputBuffer(string $buffer): string
    {
        if (!empty($buffer)) {
            $this->cache->push(
                Utils::getRequestUrl(),
                $buffer . $this->getSignature(),
                apply_filters(Hooks::FILTER_REQUEST_VARIANT, Core::DEFAULT_REQUEST_VARIANT)
            );
        }

        return $buffer;
    }


    /**
     * @return bool True, if current user can explicitly flush the cache, false otherwise.
     */
    public static function canUserFlushCache(): bool
    {
        return apply_filters(
            Hooks::FILTER_USER_CAN_FLUSH_CACHE,
            current_user_can('manage_options')
        );
    }


    /**
     * Get cache signature (by default embedded in HTML comment).
     *
     * @return string
     */
    private function getSignature(): string
    {
        return apply_filters(
            Hooks::FILTER_HTML_SIGNATURE,
            sprintf(
                "%s<!-- %s | %s @ %s -->",
                PHP_EOL . PHP_EOL,
                'BC Cache',
                __('Generated', 'bc-cache'),
                date_i18n('d.m.Y H:i:s', current_time('timestamp'))
            )
        );
    }


    /**
     * @return bool True, if cache should be skipped, false otherwise.
     */
    private static function skipCache(): bool
    {
        // Only cache GET requests without query string (~ static pages).
        if (($_SERVER['REQUEST_METHOD'] !== 'GET') || !empty($_GET)) {
            return true;
        }

        // Only cache requests routed through main index.php (skip AJAX, WP-Cron, WP-CLI etc.)
        if (!Utils::isIndex()) {
            return true;
        }

        // Only cache requests for anonymous users.
        if (is_user_logged_in() || !Utils::isAnonymousUser()) {
            return true;
        }

        // Do not cache following types of requests.
        if (is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required()) {
            return true;
        }

        // Do not cache page, if WooCommerce says so.
        if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
            return true;
        }

        return apply_filters(Hooks::FILTER_SKIP_CACHE, false);
    }
}

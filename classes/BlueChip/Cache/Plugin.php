<?php
/**
 * @package BC_Cache
 */

namespace BlueChip\Cache;

class Plugin
{
    /**
     * Name of nonce used for AJAX-ified flush cache requests.
     */
    const NONCE_FLUSH_CACHE_REQUEST = 'bc-cache/nonce:flush-cache-request';

    /**
     * Name of transient used to cache cache size.
     */
    const TRANSIENT_CACHE_SIZE = 'bc-cache/transient:cache-size';

    /**
     * List of actions that trigger cache flushing including priority with which the flush method is hooked.
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
    private $plugin_basename;

    /**
     * @var string
     */
    private $plugin_directory;

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
        $this->flushCache();
    }


    /**
     * Perform deactivation tasks.
     *
     * @internal Method should be run on plugin deactivation.
     * @link https://developer.wordpress.org/plugins/the-basics/activation-deactivation-hooks/
     */
    public function deactivate()
    {
        $this->flushCache();
    }


    /**
     * Perform uninstallation tasks.
     *
     * @internal Method should be run on plugin uninstall.
     * @link https://developer.wordpress.org/plugins/the-basics/uninstall-methods/
     */
    public function uninstall()
    {
        $this->flushCache();
        delete_transient(self::TRANSIENT_CACHE_SIZE);
    }


    /**
     * @param string $plugin_filename
     */
    public function __construct(string $plugin_filename)
    {
        $this->plugin_basename = plugin_basename($plugin_filename);
        $this->plugin_directory = dirname($plugin_filename);
        $this->plugin_filename = $plugin_filename;
        $this->cache = new Core();
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
    }


    /**
     * Perform initialization tasks.
     *
     * @internal Method should be invoked in `init` hook.
     */
    public function init()
    {
        // Init the cache.
        $this->cache->init();

        // Add actions to flush entire cache.
        foreach (apply_filters(Hooks::FILTER_FLUSH_HOOKS, self::FLUSH_CACHE_HOOKS) as $hook => $priority) {
            add_action($hook, [$this, 'flushCacheOnce'], $priority, 0);
        }

        // Add action to flush entire cache manually with do_action().
        add_action(Hooks::ACTION_FLUSH_CACHE, [$this, 'flushCacheOnce'], 10, 0);

        // Add flush icon to admin bar.
        if (is_admin_bar_showing() && $this->canUserFlushCache()) {
            add_action('admin_bar_init', [$this, 'enqueueFlushIconAssets'], 10, 0);
            add_action('admin_bar_menu', [$this, 'addFlushIcon'], 90, 1);
        }

        if (is_admin()) {
            if ($this->canUserFlushCache()) {
                add_filter('dashboard_glance_items', [$this, 'addDashboardInfo'], 10, 1);
            }
        } else {
            // Add action to catch output buffer.
            add_action('template_redirect', [$this, 'startOutputBuffering'], 0, 0);
        }
    }


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
            ]
        );
    }


    /**
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public function addFlushIcon(\WP_Admin_Bar $wp_admin_bar)
    {
        $wp_admin_bar->add_node([
            'id' 	 => 'bc-cache',
            'parent' => 'top-secondary',
            'title'	 => '<span class="ab-icon dashicons"></span><span class="bc-cache-spinner"></span>',
            'meta'   => [
                'title' => __('Flush the cache', 'bc-cache'),
            ],
        ]);
    }


    /**
     * Add info about cache size to "At a Glance" box on dashboard.
     *
     * @param array $items
     * @return array
     */
    public function addDashboardInfo(array $items): array
    {
        $size = $this->getCacheSize();

        $icon = sprintf(
            '<svg style="width: 20px; height: 20px; fill: #82878c; vertical-align: middle;" aria-hidden="true" role="img"><use xlink:href="%s#bc-cache-icon-%s"></svg>',
            plugins_url('assets/icons.svg', $this->plugin_filename),
            strtolower($this->cache->getName())
        );

        $cache_size = empty($size)
            ? __('Empty cache', 'bc-cache')
            : sprintf(__('%s cache', 'bc-cache'), size_format($size))
        ;

        $items[] = $icon . ' ' . esc_html($cache_size);

        return $items;
    }


    /**
     * Flush cache once per request only.
     *
     * @see Plugin::flushCache()
     * @return Cached result of call to flushCache().
     */
    public function flushCacheOnce(): bool
    {
        static $is_flushed = null;

        if (is_null($is_flushed)) {
            $is_flushed = $this->flushCache();
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
        if ($this->canUserFlushCache() && $this->flushCache()) {
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }


    /**
     * Start caching of output, but only if current page should be cached.
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
     * @internal Updates cache size transient as a side effect.
     * @param string $buffer
     * @return string
     */
    public function handleOutputBuffer(string $buffer): string
    {
        if (!empty($buffer)) {
            try {
                $bytes_written = $this->cache->push(
                    Utils::getRequestUrl(),
                    $buffer . $this->getSignature()
                );

                // If cache size transient exists, update it.
                if (($cache_size = get_transient(self::TRANSIENT_CACHE_SIZE)) !== false) {
                    set_transient(self::TRANSIENT_CACHE_SIZE, $cache_size + $bytes_written);
                }
            } catch (Exception $e) {
                trigger_error($e, E_USER_WARNING);
            }
        }

        return $buffer;
    }


    /**
     * Flush cache safely: catch and log any exceptions.
     *
     * @internal Updates (or deletes) cache size transient as a side effect.
     * @return bool True on success (~ there's been no error reported), false otherwise.
     */
    private function flushCache(): bool
    {
        try {
            $this->cache->flush();
            // Update cache size transient: cache is empty.
            set_transient(self::TRANSIENT_CACHE_SIZE, 0);
            return true;
        } catch (Exception $e) {
            trigger_error($e, E_USER_WARNING);
            // Delete cache size transient as cache size is unknown due I/O error.
            delete_transient(self::TRANSIENT_CACHE_SIZE);
            return false;
        }
    }


    /**
     * Get cache size safely: catch I/O exceptions, use transient cache to not slow down system too much.
     *
     * @return int
     */
    private function getCacheSize(): int
    {
        if (($cache_size = get_transient(self::TRANSIENT_CACHE_SIZE)) === false) {
            try {
                $cache_size = $this->cache->getSize();
                set_transient(self::TRANSIENT_CACHE_SIZE, $cache_size);
            } catch (Exception $e) {
                trigger_error($e, E_USER_WARNING);
                $cache_size = 0; // TODO: this is possibly misleading.
            }
        }

        return $cache_size;
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
     * @return bool True, if current user can explicitly flush the cache, false otherwise.
     */
    private function canUserFlushCache(): bool
    {
        return apply_filters(
            Hooks::FILTER_USER_CAN_FLUSH_CACHE,
            current_user_can('manage_options')
        );
    }


    /**
     * @return bool True, if cache should be skipped, false otherwise.
     */
    private static function skipCache(): bool
    {
		// Only cache requests without any variables (~ static pages)
		if (!empty($_POST) || !empty($_GET)) {
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

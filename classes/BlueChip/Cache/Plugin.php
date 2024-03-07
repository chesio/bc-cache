<?php

declare(strict_types=1);

namespace BlueChip\Cache;

class Plugin
{
    /**
     * @var string Path to root cache directory
     */
    public const CACHE_DIR = WP_CONTENT_DIR . '/cache/bc-cache';

    /**
     * @var string URL of root cache directory
     */
    public const CACHE_URL = WP_CONTENT_URL . '/cache/bc-cache';

    /**
     * @var string Path to cache core lock file - must be outside of cache directory!
     */
    private const CACHE_FILE_LOCK_FILENAME = WP_CONTENT_DIR . '/cache/.bc-cache.lock';

    /**
     * @var string Path to cache feeder lock file - must be outside of cache directory!
     */
    private const FEEDER_FILE_LOCK_FILENAME = WP_CONTENT_DIR . '/cache/.bc-cache-feeder.lock';

    /**
     * @var string Default name of cookie to denote front-end users.
     */
    private const FRONTEND_USER_COOKIE_NAME = 'bc_cache_is_fe_user';

    /**
     * @var string Default value of cookie to denote front-end users.
     */
    private const FRONTEND_USER_COOKIE_VALUE = 'true';

    /**
     * @var string Name of nonce used for AJAX-ified flush cache requests.
     */
    private const NONCE_FLUSH_CACHE_REQUEST = 'bc-cache/nonce:flush-cache-request';

    /**
     * @var string Name of transient used to keep cache age and size information.
     */
    private const TRANSIENT_CACHE_INFO = 'bc-cache/transient:cache-info';

    /**
     * @var array<string,int> List of default actions that trigger cache flushing including priority with which the flush method is hooked.
     */
    private const FLUSH_CACHE_HOOKS = [
        // Core code changes
        '_core_updated_successfully' => 10,
        // Front-end layout changes
        'switch_theme' => 10,
        'wp_update_nav_menu' => 10,
        // Post is unpublished (but not trashed yet) - see also: registerPostType()
        'publish_to_draft' => 10,
        'publish_to_future' => 10,
        'publish_to_pending' => 10,
        // Comment content changes
        'comment_post' => 10,
        'edit_comment' => 10,
        'delete_comment' => 10,
        'wp_set_comment_status' => 10,
        'wp_update_comment_count' => 10,
        // Widgets are manipulated
        'update_option_sidebars_widgets' => 10,
    ];

    /**
     * @var string[] List of whitelisted query string fields (these do not prevent cache write).
     */
    private const WHITELISTED_QUERY_STRING_FIELDS = [
        // https://developers.google.com/gtagjs/devguide/linker
        '_gl',
        // https://support.google.com/searchads/answer/7342044
        'gclid',
        'gclsrc',
        // https://www.facebook.com/business/help/330994334179410 "URL in ad can't contain Facebook Click ID" section
        'fbclid',
        // https://help.ads.microsoft.com/apex/index/3/en/60000
        'msclkid',
        // https://en.wikipedia.org/wiki/UTM_parameters
        'utm_campaign',
        'utm_content',
        'utm_medium',
        'utm_source',
        'utm_term',
    ];

    /**
     * @var string Header sent with cached entries holding timestamp of cache entry creation.
     */
    private const CACHE_RESPONSE_HTTP_HEADER = 'X-BC-Cache-Generated';

    /**
     * @var string[] List of HTTP response header types that can be served for cached entry.
     */
    private const WHITELISTED_RESPONSE_HTTP_HEADER_TYPES = [
        'Link',
        'X-Pingback',
        'X-Robots-Tag',
        self::CACHE_RESPONSE_HTTP_HEADER,
    ];


    private string $plugin_filename;

    private Core $cache;

    private Info $cache_info;

    private Lock $cache_lock;

    private Lock $feeder_lock;

    /**
     * @var Crawler|null Crawler instance if cache warm up is enabled, null otherwise.
     */
    private ?Crawler $cache_crawler = null;

    /**
     * @var Feeder|null Feeder instance if cache warm up is enabled, null otherwise.
     */
    private ?Feeder $cache_feeder = null;

    /**
     * @var Viewer Viewer instance (initialized only in admin context).
     */
    private Viewer $cache_viewer;

    /**
     * @var bool|null Null if cache has not been flushed yet in this request or cache flush status.
     */
    private ?bool $cache_is_flushed = null;


    /**
     * Perform activation and installation tasks.
     *
     * @internal Method should be run on plugin activation.
     *
     * @link https://developer.wordpress.org/plugins/the-basics/activation-deactivation-hooks/
     */
    public function activate(): void
    {
        // Attempt to create cache root directory.
        if (!$this->cache->setUp()) {
            // https://pento.net/2014/02/18/dont-let-your-plugin-be-activated-on-incompatible-sites/
            deactivate_plugins(plugin_basename($this->plugin_filename));
            wp_die(
                __('BC Cache failed to create root cache directory!', 'bc-cache'),
                __('BC Cache activation failed', 'bc-cache'),
                ['back_link' => true]
            );
        }

        // Note: feeder lock has to be set up before cache feeder is set up.
        $this->feeder_lock->setUp();
        if ($this->cache_feeder) {
            $this->cache_feeder->setUp();
        }
        $this->cache_info->setUp();
        $this->cache_lock->setUp();
    }


    /**
     * Perform deactivation tasks.
     *
     * @internal Method should be run on plugin deactivation.
     *
     * @link https://developer.wordpress.org/plugins/the-basics/activation-deactivation-hooks/
     */
    public function deactivate(): void
    {
        if ($this->cache_crawler) {
            $this->cache_crawler->deactivate();
        }
        $this->cache->tearDown();
        if ($this->cache_feeder) {
            $this->cache_feeder->tearDown();
        }
        $this->feeder_lock->tearDown();
        $this->cache_info->tearDown();
        $this->cache_lock->tearDown();
    }


    /**
     * @param string $plugin_filename Absolute path to plugin main file.
     * @param bool $file_locking_enabled True if file locking should be enabled, false otherwise.
     * @param bool $warm_up_enabled True if cache warm up should be enabled, false otherwise.
     */
    public function __construct(string $plugin_filename, bool $file_locking_enabled, bool $warm_up_enabled)
    {
        $this->plugin_filename = $plugin_filename;
        $this->cache_info = new Info(self::TRANSIENT_CACHE_INFO);

        // Initialize locks as either proper file locks or dummy locks.
        $this->cache_lock = $file_locking_enabled ? new FileLock(self::CACHE_FILE_LOCK_FILENAME) : new DummyLock();
        $this->feeder_lock = ($warm_up_enabled && $file_locking_enabled)
            ? new FileLock(self::FEEDER_FILE_LOCK_FILENAME)
            : new DummyLock()
        ;

        // Initialize core module and optional features.
        $this->cache = new Core(self::CACHE_DIR, $this->cache_info, $this->cache_lock);
        if ($warm_up_enabled) {
            $this->cache_feeder = new Feeder($this->cache, $this->feeder_lock);
            $this->cache_crawler = new Crawler($this->cache_feeder);
        }
    }


    /**
     * Load the plugin by hooking into WordPress actions and filters.
     *
     * @action https://developer.wordpress.org/reference/hooks/plugins_loaded/
     */
    public function load(): void
    {
        // Register initialization method.
        add_action('init', $this->init(...), 10, 0);

        // Register method handling AJAX call from admin bar icon (or elsewhere).
        add_action('wp_ajax_bc_cache_flush_cache', $this->processFlushRequest(...), 10, 0);

        // Integrate with WP-CLI.
        add_action('cli_init', function () {
            \WP_CLI::add_command(
                'bc-cache',
                new Cli($this->cache, $this->cache_crawler, $this->cache_feeder)
            );
        });

        // Activate features that must be explicitly supported by active theme.
        add_action('after_setup_theme', $this->activateThemeFeatures(...), 20, 0);

        // Listen for registration of (public) post types.
        // They may (in fact should) happen as late as in init hook, therefore special handling is required.
        add_action('registered_post_type', $this->registerPostType(...), 10, 2);

        // Listen for registration of (public) taxonomies.
        // They may (in fact should) happen as late as in init hook, therefore special handling is required.
        add_action('registered_taxonomy', $this->registerTaxonomy(...), 10, 3);
    }


    /**
     * Perform initialization tasks.
     *
     * @action https://developer.wordpress.org/reference/hooks/init/
     */
    private function init(): void
    {
        // Activate integrations with 3rd party plugins - must be done early in this method!
        Integrations::initialize();

        // Add Disallow section to robots.txt.
        add_filter('robots_txt', $this->alterRobotsTxt(...), 10, 1);

        // Add actions to flush entire cache.
        foreach (apply_filters(Hooks::FILTER_FLUSH_HOOKS, self::FLUSH_CACHE_HOOKS) as $hook => $priority) {
            add_action($hook, $this->flushCacheOnce(...), $priority, 0);
        }

        // Add action to flush entire cache manually with do_action().
        add_action(Hooks::ACTION_FLUSH_CACHE, $this->flushCacheOnce(...), 10, 0);

        // Add flush icon to admin bar.
        if (is_admin_bar_showing() && Utils::canUserFlushCache()) {
            add_action('admin_bar_init', $this->enqueueFlushIconAssets(...), 10, 0);
            add_action('admin_bar_menu', $this->addFlushIcon(...), 90, 1);
        }

        if (is_admin()) {
            // Initialize cache viewer.
            $this->cache_viewer = new Viewer($this->cache, $this->cache_crawler, $this->cache_feeder);
            $this->cache_viewer->init();

            if (Utils::canUserFlushCache()) {
                add_filter('dashboard_glance_items', $this->addDashboardInfo(...), 10, 1);
                add_action('rightnow_end', $this->enqueueDashboardAssets(...), 10, 0);
            }
        } else {
            // Add action to catch output buffer.
            // https://make.wordpress.org/core/2022/10/10/moving-the-send_headers-action-to-later-in-the-load/
            add_action('send_headers', $this->startOutputBuffering(...), 0, 0);
        }

        if ($this->cache_crawler) {
            // Initialize warm up crawler.
            $this->cache_crawler->init();

            // Cache has been flushed so (maybe) warm it up again?
            add_action(Hooks::ACTION_CACHE_FLUSHED, $this->warmUp(...), 10, 1);
        }
    }


    /**
     * Register cache flush hooks for public post types (including built-in ones).
     *
     * @action https://developer.wordpress.org/reference/hooks/registered_post_type/
     */
    private function registerPostType(string $post_type, \WP_Post_Type $post_type_object): void
    {
        if (apply_filters(Hooks::FILTER_IS_PUBLIC_POST_TYPE, $post_type_object->public, $post_type)) {
            // Flush cache when a public post type is published (created or edited) or trashed.
            // https://developer.wordpress.org/reference/hooks/new_status_post-post_type/
            add_action("publish_{$post_type}", $this->flushCacheOnce(...), 10, 0);
            add_action("trash_{$post_type}", $this->flushCacheOnce(...), 10, 0);
        }
    }


    /**
     * Register cache flush hooks for terms from public taxonomies.
     *
     * @action https://developer.wordpress.org/reference/hooks/registered_taxonomy/
     *
     * @param string $taxonomy
     * @param array<int,string>|string $object_type Object type or array of object types.
     * @param array<string,mixed> $taxonomy_object Public properties of \WP_Taxonomy class as array.
     */
    private function registerTaxonomy(string $taxonomy, array|string $object_type, array $taxonomy_object): void
    {
        if (apply_filters(Hooks::FILTER_IS_PUBLIC_TAXONOMY, $taxonomy_object['public'], $taxonomy)) {
            // Flush cache when a term from public taxonomy is created, deleted or edited.
            // https://developer.wordpress.org/reference/hooks/new_status_post-post_type/
            add_action("create_{$taxonomy}", $this->flushCacheOnce(...), 10, 0);
            add_action("delete_{$taxonomy}", $this->flushCacheOnce(...), 10, 0);
            add_action("edited_{$taxonomy}", $this->flushCacheOnce(...), 10, 0);
        }
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/after_setup_theme/
     */
    private function activateThemeFeatures(): void
    {
        if (current_theme_supports('bc-cache', ThemeFeatures::CACHING_FOR_FRONTEND_USERS)) {
            // Allow special cookie to be set for front-end users to enable serving of cached content to them.
            add_action('set_logged_in_cookie', $this->setFrontendUserCookie(...), 10, 4);
            add_action('clear_auth_cookie', $this->clearFrontendUserCookie(...), 10, 0);
        }
    }


    /**
     * @filter https://developer.wordpress.org/reference/hooks/robots_txt/
     */
    private function alterRobotsTxt(string $data): string
    {
        // Get path component of cache directory URL.
        $path = \parse_url(self::CACHE_URL, PHP_URL_PATH);
        // Disallow direct access to cache directory.
        return $data . PHP_EOL
            . 'User-agent: *' . PHP_EOL
            . \sprintf('Disallow: %s/', $path) . PHP_EOL
        ;
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/admin_bar_init/
     */
    private function enqueueFlushIconAssets(): void
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
            ]
        );
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/admin_bar_menu/
     *
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    private function addFlushIcon(\WP_Admin_Bar $wp_admin_bar): void
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
     * @param string[] $items
     *
     * @return string[]
     */
    private function addDashboardInfo(array $items): array
    {
        $size = $this->cache->getSize();

        $icon = \sprintf(
            '<svg style="width: 20px; height: 20px; fill: #82878c; float: left; margin-right: 5px;" aria-hidden="true" role="img"><use xlink:href="%s#bc-cache-icon-hdd"></svg>',
            plugins_url('assets/icons.svg', $this->plugin_filename)
        );

        $cache_size = \is_int($size)
            ? (empty($size)
                ? __('Empty cache', 'bc-cache')
                : \sprintf(__('%s cache', 'bc-cache'), size_format($size))
            )
            : __('Unknown size', 'bc-cache')
        ;

        // Label has ID, so we can target (update) it via JavaScript.
        $label = '<span id="bc-cache-size">' . esc_html($cache_size) . '</span>';

        // Wrap icon and label in a link to cache viewer.
        $items[] = '<a class="bc-cache-size" href="' . $this->cache_viewer->getUrl() . '">' . $icon . ' ' . $label . '</a>';

        return $items;
    }


    /**
     * Print short HTML snippet with CSS rules for cache size information in "At a Glance" box.
     *
     * @action https://developer.wordpress.org/reference/hooks/admin_print_footer_scripts/
     */
    private function printDashboardStyles(): void
    {
        echo '<style>#dashboard_right_now li .bc-cache-size:before { content: ""; display: none; }</style>';
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/rightnow_end/
     */
    private function enqueueDashboardAssets(): void
    {
        // Print the styles in the footer.
        add_action('admin_print_footer_scripts', $this->printDashboardStyles(...), 10, 0);
    }


    /**
     * Flush cache once per request only.
     *
     * @see Core::flush()
     */
    private function flushCacheOnce(): void
    {
        $this->cache_is_flushed ??= $this->cache->flush();
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
     * Start caching of output, but only if current page should be cached.
     *
     * @action https://developer.wordpress.org/reference/hooks/template_redirect/
     */
    private function startOutputBuffering(): void
    {
        if (!self::skipCache()) {
            \ob_start($this->handleOutputBuffer(...));
        }
    }


    /**
     * Push $buffer to cache and return it on output.
     */
    private function handleOutputBuffer(string $buffer, int $phase): string
    {
        // If this is the final output buffering operation and buffer is not empty, write buffer contents to cache.
        if (($phase & PHP_OUTPUT_HANDLER_FINAL) && ($buffer !== '')) {
            $item = new Item(
                Utils::getRequestUrl(),
                apply_filters(Hooks::FILTER_REQUEST_VARIANT, Core::DEFAULT_REQUEST_VARIANT)
            );

            // Generate cache timestamp to include in HTML response body and HTTP response header.
            $cache_timestamp_format = apply_filters(Hooks::FILTER_CACHE_GENERATION_TIMESTAMP_FORMAT, 'Y-m-d H:i:s');
            $cache_timestamp = wp_date($cache_timestamp_format, \time()) ?: '';

            // Grab headers generated by PHP for this request.
            $headers = \headers_list();

            $data = $buffer;

            // Only append signature to HTML data.
            if (Utils::getResponseMimeType($headers) === 'text/html') {
                $data .= $this->getSignature($cache_timestamp);
            }

            // Add X-BC-Cache-Generated header to list of headers.
            $headers[] = \sprintf('%s: %s', self::CACHE_RESPONSE_HTTP_HEADER, $cache_timestamp);

            // Grab header types to keep.
            $header_types_to_keep = apply_filters(
                Hooks::FILTER_CACHED_RESPONSE_HEADERS,
                self::WHITELISTED_RESPONSE_HTTP_HEADER_TYPES
            );

            // Always keep Content-Type header.
            $header_types_to_keep[] = 'Content-Type';

            $headers = Utils::filterHttpHeaders($headers, $header_types_to_keep);

            $this->cache->push($item, $headers, $data);
        }

        return $buffer;
    }


    /**
     * Get cache signature (by default embedded in HTML comment).
     *
     * @param string $cache_timestamp Timestamp of cache creation
     *
     * @return string
     */
    private function getSignature(string $cache_timestamp): string
    {
        return apply_filters(
            Hooks::FILTER_HTML_SIGNATURE,
            \sprintf(
                "%s<!-- %s | %s @ %s -->",
                PHP_EOL . PHP_EOL,
                'BC Cache',
                __('Generated', 'bc-cache'),
                $cache_timestamp
            )
        );
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/set_logged_in_cookie/
     */
    private function setFrontendUserCookie(string $logged_in_cookie, int $expire, int $expiration, int $user_id): void
    {
        if (($user = get_user_by('id', $user_id)) === false) {
            return;
        }

        if (Utils::isFrontendUser($user)) {
            \setcookie(
                apply_filters(Hooks::FILTER_FRONTEND_USER_COOKIE_NAME, self::FRONTEND_USER_COOKIE_NAME),
                apply_filters(Hooks::FILTER_FRONTEND_USER_COOKIE_VALUE, self::FRONTEND_USER_COOKIE_VALUE),
                $expire,
                COOKIEPATH,
                COOKIE_DOMAIN,
                false,
                true
            );
        }
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/clear_auth_cookie/
     */
    private function clearFrontendUserCookie(): void
    {
        \setcookie(
            apply_filters(Hooks::FILTER_FRONTEND_USER_COOKIE_NAME, self::FRONTEND_USER_COOKIE_NAME),
            ' ',
            time() - YEAR_IN_SECONDS,
            COOKIEPATH,
            COOKIE_DOMAIN
        );
    }


    private function warmUp(bool $tear_down): void
    {
        // If not deactivating plugin instance, reset feeder and (re)activate crawler.
        if (!$tear_down && $this->cache_feeder && $this->cache_crawler) {
            $this->cache_feeder->reset();
            $this->cache_crawler->activate();
        }
    }


    /**
     * @return bool True if cache should be skipped, false otherwise.
     */
    private static function skipCache(): bool
    {
        // Only cache GET requests with whitelisted query string fields.
        if (($_SERVER['REQUEST_METHOD'] !== 'GET') || !self::checkQueryString(\array_keys($_GET))) {
            return true;
        }

        // Only cache requests routed through main index.php and using themes.
        if (!wp_using_themes()) {
            return true;
        }

        // Only cache requests that return no personalized content.
        if (Utils::hasUserPersonalizedContent()) {
            return true;
        }

        // Only cache requests for anonymous or (if the theme supports it) front-end users.
        if (!(Utils::isAnonymousUser() || (current_theme_supports('bc-cache', ThemeFeatures::CACHING_FOR_FRONTEND_USERS) && Utils::isFrontendUser()))) {
            return true;
        }

        // Do not cache following types of requests.
        if (is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required()) {
            return true;
        }

        // Do not cache page if website is in recovery mode.
        if (wp_is_recovery_mode()) {
            return true;
        }

        // Do not cache page if WooCommerce says so.
        if (\defined('DONOTCACHEPAGE') && \constant('DONOTCACHEPAGE')) {
            return true;
        }

        return apply_filters(Hooks::FILTER_SKIP_CACHE, false);
    }


    /**
     * Check whether query string $fields allow page to be cached.
     *
     * @param string[] $fields Query string fields (keys).
     *
     * @return bool True, if query string $fields contain only whitelisted values, false otherwise.
     */
    private static function checkQueryString(array $fields): bool
    {
        $whitelisted_fields = apply_filters(
            Hooks::FILTER_WHITELISTED_QUERY_STRING_FIELDS,
            self::WHITELISTED_QUERY_STRING_FIELDS
        );

        // All $fields must be present in whitelist.
        return \array_diff($fields, $whitelisted_fields) === [];
    }
}

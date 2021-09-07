<?php

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
     * @var string Path to cache lock file - must be outside of cache directory!
     */
    private const CACHE_LOCK_FILENAME = WP_CONTENT_DIR . '/cache/.bc-cache.lock';

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
     * @var array List of default actions that trigger cache flushing including priority with which the flush method is hooked.
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
     * @var array List of whitelisted query string fields (these do not prevent cache write).
     */
    private const WHITELISTED_QUERY_STRING_FIELDS = [
        // https://developers.google.com/gtagjs/devguide/linker
        '_gl',
        // https://support.google.com/searchads/answer/7342044
        'gclid',
        'gclsrc',
        // https://www.facebook.com/business/help/330994334179410 "URL in ad can't contain Facebook Click ID" section
        'fbclid',
        // https://en.wikipedia.org/wiki/UTM_parameters
        'utm_campaign',
        'utm_content',
        'utm_medium',
        'utm_source',
        'utm_term',
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
     * @var \BlueChip\Cache\Info
     */
    private $cache_info;

    /**
     * @var \BlueChip\Cache\Lock
     */
    private $cache_lock;

    /**
     * @var \BlueChip\Cache\Crawler
     */
    private $cache_crawler;

    /**
     * @var \BlueChip\Cache\Feeder
     */
    private $cache_feeder;


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

        $this->cache_feeder->setUp();
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
        $this->cache_crawler->deactivate();
        $this->cache->tearDown();
        $this->cache_feeder->tearDown();
        $this->cache_info->tearDown();
        $this->cache_lock->tearDown();
    }


    /**
     * @param string $plugin_filename
     */
    public function __construct(string $plugin_filename)
    {
        $this->plugin_filename = $plugin_filename;
        $this->cache_info = new Info(self::TRANSIENT_CACHE_INFO);
        $this->cache_lock = new Lock(self::CACHE_LOCK_FILENAME);
        $this->cache_feeder = new Feeder();
        $this->cache = new Core(self::CACHE_DIR, $this->cache_info, $this->cache_lock);
        $this->cache_crawler = new Crawler($this->cache, $this->cache_feeder);
    }


    /**
     * Load the plugin by hooking into WordPress actions and filters.
     *
     * @internal Method should be invoked immediately on plugin load.
     */
    public function load(): void
    {
        // Register initialization method.
        add_action('init', [$this, 'init'], 10, 0);

        // Register method handling AJAX call from admin bar icon (or elsewhere).
        add_action('wp_ajax_bc_cache_flush_cache', [$this, 'processFlushRequest'], 10, 0);

        // Integrate with WP-CLI.
        add_action('cli_init', function () {
            \WP_CLI::add_command('bc-cache', new Cli($this->cache));
        });

        // Activate features that must be explicitly supported by active theme.
        add_action('after_setup_theme', [$this, 'activateThemeFeatures'], 20, 0);

        // Listen for registration of (public) post types.
        // They may (in fact should) happen as late as in init hook, therefore special handling is required.
        add_action('registered_post_type', [$this, 'registerPostType'], 10, 2);

        // Listen for registration of (public) taxonomies.
        // They may (in fact should) happen as late as in init hook, therefore special handling is required.
        add_action('registered_taxonomy', [$this, 'registerTaxonomy'], 10, 3);
    }


    /**
     * Perform initialization tasks.
     *
     * @action https://developer.wordpress.org/reference/hooks/init/
     */
    public function init(): void
    {
        // Activate integrations with 3rd party plugins - must be done early in this method!
        Integrations::initialize();

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
            (new Viewer($this->cache, $this->cache_feeder))->init();

            if (self::canUserFlushCache()) {
                add_filter('dashboard_glance_items', [$this, 'addDashboardInfo'], 10, 1);
                add_action('rightnow_end', [$this, 'enqueueDashboardAssets'], 10, 0);
            }
        } else {
            // Add action to catch output buffer.
            add_action('template_redirect', [$this, 'startOutputBuffering'], 0, 0);
        }

        if (self::isCacheWarmUpEnabled()) {
            // Initialize warm up crawler.
            $this->cache_crawler->init();

            // Cache has been flushed so (maybe) warm it up again?
            add_action(Hooks::ACTION_CACHE_FLUSHED, [$this, 'warmUp'], 10, 1);
        }
    }


    /**
     * Register cache flush hooks for public post types (including built-in ones).
     *
     * @action https://developer.wordpress.org/reference/hooks/registered_post_type/
     *
     * @param string $post_type
     * @param \WP_Post_Type $post_type_object
     */
    public function registerPostType(string $post_type, \WP_Post_Type $post_type_object): void
    {
        if (apply_filters(Hooks::FILTER_IS_PUBLIC_POST_TYPE, $post_type_object->public, $post_type)) {
            // Flush cache when a public post type is published (created or edited) or trashed.
            // https://developer.wordpress.org/reference/hooks/new_status_post-post_type/
            add_action("publish_{$post_type}", [$this, 'flushCacheOnce'], 10, 0);
            add_action("trash_{$post_type}", [$this, 'flushCacheOnce'], 10, 0);
        }
    }


    /**
     * Register cache flush hooks for terms from public taxonomies.
     *
     * @action https://developer.wordpress.org/reference/hooks/registered_taxonomy/
     *
     * @param string $taxonomy
     * @param array|string $object_type Object type or array of object types.
     * @param array $taxonomy_object Public properties of \WP_Taxonomy class as array.
     */
    public function registerTaxonomy(string $taxonomy, $object_type, array $taxonomy_object): void
    {
        if (apply_filters(Hooks::FILTER_IS_PUBLIC_TAXONOMY, $taxonomy_object['public'], $taxonomy)) {
            // Flush cache when a term from public taxonomy is created, deleted or edited.
            // https://developer.wordpress.org/reference/hooks/new_status_post-post_type/
            add_action("create_{$taxonomy}", [$this, 'flushCacheOnce'], 10, 0);
            add_action("delete_{$taxonomy}", [$this, 'flushCacheOnce'], 10, 0);
            add_action("edited_{$taxonomy}", [$this, 'flushCacheOnce'], 10, 0);
        }
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/after_setup_theme/
     */
    public function activateThemeFeatures(): void
    {
        if (current_theme_supports('bc-cache', ThemeFeatures::CACHING_FOR_FRONTEND_USERS)) {
            // Allow special cookie to be set for front-end users to enable serving of cached content to them.
            add_action('set_logged_in_cookie', [$this, 'setFrontendUserCookie'], 10, 4);
            add_action('clear_auth_cookie', [$this, 'clearFrontendUserCookie'], 10, 0);
        }
    }


    /**
     * @filter https://developer.wordpress.org/reference/hooks/robots_txt/
     *
     * @param string $data
     *
     * @return string
     */
    public function alterRobotsTxt(string $data): string
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
    public function enqueueFlushIconAssets(): void
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
    public function addFlushIcon(\WP_Admin_Bar $wp_admin_bar): void
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
    public function addDashboardInfo(array $items): array
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
        $items[] = '<a class="bc-cache-size" href="' . Viewer::getUrl() . '">' . $icon . ' ' . $label . '</a>';

        return $items;
    }


    /**
     * Print short HTML snippet with CSS rules for cache size information in "At a Glance" box.
     *
     * @action https://developer.wordpress.org/reference/hooks/admin_print_footer_scripts/
     */
    public function printDashboardStyles(): void
    {
        echo '<style>#dashboard_right_now li .bc-cache-size:before { content: ""; display: none; }</style>';
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/rightnow_end/
     */
    public function enqueueDashboardAssets(): void
    {
        // Print the styles in the footer.
        add_action('admin_print_footer_scripts', [$this, 'printDashboardStyles'], 10, 0);
    }


    /**
     * Flush cache once per request only.
     *
     * @see Core::flush()
     *
     * @return bool Cached result of call to Core::flush().
     */
    public function flushCacheOnce(): bool
    {
        static $is_flushed = null;

        if ($is_flushed === null) {
            $is_flushed = $this->cache->flush();
        }

        return $is_flushed;
    }


    /**
     * Process AJAX flush request.
     *
     * @internal Should be executed in context of AJAX request only.
     */
    public function processFlushRequest(): void
    {
        // Check AJAX referer - die if invalid.
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
    public function startOutputBuffering(): void
    {
        if (!self::skipCache()) {
            \ob_start([$this, 'handleOutputBuffer']);
        }
    }


    /**
     * Push $buffer to cache and return it on output.
     *
     * @param string $buffer
     *
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
     * @return bool True if current user can explicitly flush the cache, false otherwise.
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
            \sprintf(
                "%s<!-- %s | %s @ %s -->",
                PHP_EOL . PHP_EOL,
                'BC Cache',
                __('Generated', 'bc-cache'),
                wp_date('d.m.Y H:i:s', \intval(\time()))
            )
        );
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/set_logged_in_cookie/
     *
     * @param string $logged_in_cookie
     * @param int $expire
     * @param int $expiration
     * @param int $user_id
     */
    public function setFrontendUserCookie(string $logged_in_cookie, int $expire, int $expiration, int $user_id): void
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
    public function clearFrontendUserCookie(): void
    {
        \setcookie(
            apply_filters(Hooks::FILTER_FRONTEND_USER_COOKIE_NAME, self::FRONTEND_USER_COOKIE_NAME),
            ' ',
            time() - YEAR_IN_SECONDS,
            COOKIEPATH,
            COOKIE_DOMAIN
        );
    }


    /**
     * @param bool $tear_down
     */
    public function warmUp(bool $tear_down): void
    {
        // If not deactivating plugin instance, reset feeder and (re)activate crawler.
        if (!$tear_down) {
            $this->cache_feeder->reset();
            $this->cache_crawler->activate();
        }
    }


    /**
     * Determine whether cache warm up feature should be enabled.
     *
     * @return bool True if cache warm up is enabled, false otherwise.
     */
    public static function isCacheWarmUpEnabled(): bool
    {
        return apply_filters(Hooks::FILTER_CACHE_WARM_ENABLED, true);
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
        // Note: There are no is_sitemap() etc. functions, so one has to use get_query_var() for now.
        if (is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() || get_query_var('sitemap') || get_query_var('sitemap-subtype') || get_query_var('sitemap-stylesheet')) {
            return true;
        }

        // Do not cache page if website is in recovery mode.
        if (wp_is_recovery_mode()) {
            return true;
        }

        // Do not cache page if WooCommerce says so.
        if (\defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
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

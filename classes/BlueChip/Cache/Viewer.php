<?php
/**
 * @package BC_Cache
 */

namespace BlueChip\Cache;

class Viewer
{
    /**
     * @var string Slug for cache viewer page
     */
    const ADMIN_PAGE_SLUG = 'bc-cache-view';

    /**
     * @var string Capability required to view cache viewer
     */
    const REQUIRED_CAPABILITY = 'manage_options';

    /**
     * @var \BlueChip\Cache\Core
     */
    private $cache;

    /**
     * @var \BlueChip\Cache\ListTable
     */
    private $list_table;


    /**
     * @param \BlueChip\Cache\Core $cache
     */
    public function __construct(Core $cache)
    {
        $this->cache = $cache;
    }


    /**
     * Initialize cache viewer.
     */
    public function init()
    {
        add_action('admin_menu', [$this, 'addAdminPage']);
    }


    /**
     * Register method to be run on page load.
     *
     * @link https://developer.wordpress.org/reference/hooks/load-page_hook/
     *
     * @param string $page_hook
     */
    public function setPageHook(string $page_hook)
    {
        add_action('load-' . $page_hook, [$this, 'loadPage']);
    }


    /**
     * @return string URL of viewer page.
     */
    public static function getUrl(): string
    {
        return add_query_arg('page', self::ADMIN_PAGE_SLUG, admin_url('tools.php'));
    }


    /**
     * @action https://developer.wordpress.org/reference/hooks/admin_menu/
     */
    public function addAdminPage()
    {
        $page_hook = add_management_page(
            __('BC Cache Viewer', 'bc-cache'),
            __('Cache Viewer', 'bc-cache'),
            self::REQUIRED_CAPABILITY,
            self::ADMIN_PAGE_SLUG,
            [$this, 'renderAdminPage']
        );

        if ($page_hook) {
            // If page has been added properly, register method to run on page load.
            $this->setPageHook($page_hook);
        }
    }


    /**
     * @internal ListTable instance cannot be initialized in the constructor, because \WP_List_Table is unknown to PHP
     * at the time constructor is invoked.
     *
     * @action https://developer.wordpress.org/reference/hooks/load-page_hook/
     */
    public function loadPage()
    {
        $this->list_table = new ListTable($this->cache, self::getUrl());
        $this->list_table->processActions(); // may trigger wp_redirect()
        $this->list_table->displayNotices();
        $this->list_table->prepare_items();
    }


    public function renderAdminPage()
    {
        echo '<div class="wrap">';

        // Page heading
        echo '<h1>' . esc_html__('BC Cache Viewer', 'bc-cache') . '</h1>';

        echo '<p>' . sprintf(esc_html__('Cache data are stored in %s directory.', 'bc-cache'), '<code>' . Plugin::CACHE_DIR . '</code>') . '</p>';

        if (is_int($cache_size = $this->cache->getSize())) {
            echo '<p>' . sprintf(esc_html__('Cache files occupy %s of space in total.', 'bc-cache'), '<strong>' . size_format($cache_size) . '</strong>') . '</p>';
        }

        // View table
        $this->list_table->views();
        echo '<form method="post">';
        $this->list_table->display();
        echo '</form>';

        echo '</div>';
    }
}

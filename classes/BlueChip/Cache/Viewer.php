<?php

namespace BlueChip\Cache;

class Viewer
{
    /**
     * @var string Slug for cache viewer page
     */
    private const ADMIN_PAGE_SLUG = 'bc-cache-view';

    /**
     * @var string Capability required to view cache viewer
     */
    private const REQUIRED_CAPABILITY = 'manage_options';

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
    public function init(): void
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
    public function setPageHook(string $page_hook): void
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
    public function addAdminPage(): void
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
    public function loadPage(): void
    {
        $this->list_table = new ListTable($this->cache, self::getUrl());
        $this->list_table->processActions(); // may trigger wp_redirect()
        $this->list_table->displayNotices();
        $this->list_table->prepare_items();

        $this->checkCacheSize();
    }


    public function renderAdminPage(): void
    {
        echo '<div class="wrap">';

        // Page heading
        echo '<h1>' . esc_html__('BC Cache Viewer', 'bc-cache') . '</h1>';

        // Cache directory path.
        echo '<p>';
        echo \sprintf(
            esc_html__('Cache data are stored in %s directory.', 'bc-cache'),
            '<code>' . Plugin::CACHE_DIR . '</code>'
        );
        echo '</p>';

        // Gather cache statistics (age and size), if available.
        $cache_info = [];

        if (\is_int($cache_age = $this->cache->getAge())) {
            $cache_info[] = \sprintf(
                esc_html__('Cache has been fully flushed %s ago.', 'bc-cache'),
                '<strong><abbr title="' . \wp_date('Y-m-d H:i:s', $cache_age) . '">' . human_time_diff($cache_age) . '</abbr></strong>'
            );
        }

        if (\is_int($cache_files_size = $this->list_table->getCacheFilesSize())) {
            $cache_info[] = \sprintf(
                esc_html__('Cache files occupy %s of space in total.', 'bc-cache'),
                '<strong><abbr title="' . \sprintf(_n('%d byte', '%d bytes', $cache_files_size, 'bc-cache'), $cache_files_size) . '">' . size_format($cache_files_size) . '</abbr></strong>'
            );
        }

        if ($cache_info !== []) {
            echo '<p>' . \implode(' ', $cache_info) . '</p>';
        }

        // View table
        $this->list_table->views();
        echo '<form method="post">';
        $this->list_table->display();
        echo '</form>';

        echo '</div>';
    }


    /**
     * Display a warning if total size of cache files differs from total size of files in cache folder.
     */
    private function checkCacheSize(): void
    {
        $cache_files_size = $this->list_table->getCacheFilesSize();
        $cache_size = $this->cache->getSize(true);

        if (\is_int($cache_files_size) && \is_int($cache_size) && ($cache_files_size !== $cache_size)) {
            AdminNotices::add(
                \sprintf(
                    __('Total size of recognized cache files (%s) differs from total size of all files in cache directory (%s). Please, make sure that you have set up request variants filters correctly and then flush the cache.', 'bc-cache'),
                    '<strong>' . \sprintf(_n('%d byte', '%d bytes', $cache_files_size, 'bc-cache'), $cache_files_size) . '</strong>',
                    '<strong>' . \sprintf(_n('%d byte', '%d bytes', $cache_size, 'bc-cache'), $cache_size) . '</strong>'
                ),
                AdminNotices::WARNING,
                false,
                false
            );
        }
    }
}

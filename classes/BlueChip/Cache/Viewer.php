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
     * @hook https://developer.wordpress.org/reference/hooks/admin_menu/
     */
    public function addAdminPage()
    {
        add_management_page(
            __('BC Cache Viewer', 'bc-cache'),
            __('Cache Viewer', 'bc-cache'),
            self::REQUIRED_CAPABILITY,
            self::ADMIN_PAGE_SLUG,
            [$this, 'renderAdminPage']
        );
    }


    public function renderAdminPage()
    {
        $state = $this->cache->inspect();

        ksort($state); // Sort by key (ie. path)

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('BC Cache Viewer', 'bc-cache') . '</h1>';

        echo '<p>' . sprintf(esc_html__('The paths below are relative to %s directory.', 'bc-cache'), '<code>' . Core::CACHE_DIR . '</code>') . '</p>';

        echo '<p>' . sprintf(esc_html__('Cache files occupy %s of space in total.', 'bc-cache'), '<strong>' . size_format($this->cache->getSize()) . '</strong>') . '</p>';

        echo '<table class="wp-list-table widefat fixed striped">';

        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Relative path', 'bc-cache') . '</th>';
        echo '<th>' . esc_html__('URL', 'bc-cache') . '</th>';
        echo '<th>' . esc_html__('Created', 'bc-cache') . '</th>';
        echo '<th>' . esc_html__('Size', 'bc-cache') . '</th>';
        echo '</tr>';
        echo '</thead>';

        echo '<tbody>';
        foreach ($state as $item) {
            echo '<tr>';
            echo '<td><code>' . esc_html($item['relative_path']) . '</code></td>';
            echo '<td><a href="' . esc_url($item['url']) . '">' . esc_html($item['url']) . '</a></td>';
            echo '<td>' . ($item['timestamp'] ? date('Y-m-d', $item['timestamp']) . '<br>' . date('H:i:s', $item['timestamp']) : '--') . '</td>';
            echo '<td>' . esc_html(size_format($item['size'])) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';

        echo '</table>';

        echo '</div>';
    }
}

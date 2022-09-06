<?php

namespace BlueChip\Cache;

/**
 * @link https://digwp.com/2016/05/wordpress-admin-notices/
 */
abstract class AdminNotices
{
    public const ERROR = 'notice-error';
    public const WARNING = 'notice-warning';
    public const SUCCESS = 'notice-success';
    public const INFO = 'notice-info';

    /**
     * Add dismissible admin notice with given $message of given $type.
     *
     * @link https://make.wordpress.org/core/2015/04/23/spinners-and-dismissible-admin-notices-in-4-2/
     *
     * @param string $message Message to display in admin notice.
     * @param string $type [optional] Type: 'notice-error', 'notice-warning', 'notice-success' or 'notice-info] (default).
     * @param bool $is_dismissible [optional] Should the notice be dismissible? Default is true.
     * @param bool $escape_html [optional] Should the content of message be HTML escaped? Default is true.
     */
    public static function add(string $message, string $type = self::INFO, bool $is_dismissible = true, bool $escape_html = true): void
    {
        $classes = \implode(' ', \array_filter(['notice', $type, $is_dismissible ? 'is-dismissible' : '']));
        add_action('admin_notices', function () use ($message, $classes, $escape_html) {
            echo '<div class="' . $classes . '">';
            echo '<p>' . ($escape_html ? esc_html($message) : $message) . '</p>';
            echo '</div>';
        });
    }
}

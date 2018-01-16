<?php
/**
 * @package BC_Cache
 */

namespace BlueChip\Cache;

abstract class Utils
{
    /**
     * @return string URL of current request.
     */
    public static function getRequestUrl(): string
    {
        return (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }


    /**
     * Check, whether user interacted with the site in any way that would make him see personalized content.
     * @return bool True, if user seems to be just a regular Anonymous Joe, false otherwise.
     */
    public static function isAnonymousUser(): bool
    {
        if (empty($_COOKIE)) {
            return true;
        }

        foreach (array_keys($_COOKIE) as $cookie_name) {
            if (preg_match('/^(wp-postpass|wordpress_logged_in|comment_author)_/', $cookie_name)) {
                return false;
            }
        }

        return true;
    }


    /**
     * @return bool
     */
    public static function isIndex(): bool
    {
        return basename($_SERVER['SCRIPT_NAME']) === 'index.php';
    }
}

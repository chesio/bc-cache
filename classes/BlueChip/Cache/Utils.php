<?php
/**
 * @package BC_Cache
 */

namespace BlueChip\Cache;

abstract class Utils
{
    /**
     * @param string $format Format to display the date.
     * @param int $timestamp Unix timestamp.
     * @return string Date represented by Unix $timestamp in requested $format and time zone of WordPress installation.
     */
    public static function formatWpDateTime(string $format, int $timestamp): string
    {
        return (new \DateTime('@' . $timestamp))->setTimezone(self::getWpTimezone())->format($format);
    }


    /**
     * @return string URL of current request.
     */
    public static function getRequestUrl(): string
    {
        return (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }


    /**
     * @link https://github.com/Rarst/wpdatetime/blob/0.3/src/WpDateTimeZone.php
     * @return \DateTimeZone Time zone of WordPress installation.
     */
    public static function getWpTimezone(): \DateTimeZone
    {
        $timezone_string = get_option('timezone_string');
        if (!empty($timezone_string)) {
            return new \DateTimeZone($timezone_string);
        }
        $offset  = get_option('gmt_offset');
        $hours   = (int) $offset;
        $minutes = abs(($offset - (int) $offset) * 60);
        $offset  = sprintf('%+03d:%02d', $hours, $minutes);
        return new \DateTimeZone($offset);
    }


    /**
     * Check, whether user interacted with the site in any way that would make him see personalized content.
     * @return bool True if user seems to be just a regular Anonymous Joe, false otherwise.
     */
    public static function isAnonymousUser(): bool
    {
        if (empty($_COOKIE)) {
            return true;
        }

        foreach (array_keys($_COOKIE) as $cookie_name) {
            if (preg_match('/^(wp-postpass|wordpress_logged_in|comment_author)_/', (string) $cookie_name)) {
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
        return ( defined('WP_USE_THEMES') && WP_USE_THEMES );
    }
}

<?php

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
     * Check whether current user might see personalized content.
     *
     * Check covers users who have:
     * - comment form prefilled
     * - password-protected post access
     *
     * @return bool
     */
    public static function hasUserPersonalizedContent(): bool
    {
        if (empty($_COOKIE)) {
            return false;
        }

        foreach (\array_keys($_COOKIE) as $cookie_name) {
            if (\preg_match('/^(wp-postpass|comment_author)_/', (string) $cookie_name)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Check whether current user can be considered as anonymous.
     *
     * @return bool True if user seems to be just a regular Anonymous Joe, false otherwise.
     */
    public static function isAnonymousUser(): bool
    {
        return !is_user_logged_in();
    }


    /**
     * @param \WP_User|null $user User to check - if null, current user is checked.
     *
     * @return bool True if user is front-end user and can see cached content, false otherwise.
     */
    public static function isFrontendUser(?\WP_User $user = null): bool
    {
        if ($user === null) {
            $user = wp_get_current_user();
        }

        // Get capabilities that front-end user should *only* have.
        // Note: the 'customer' capability is a WooCommerce thing.
        $frontend_user_caps = apply_filters(Hooks::FILTER_FRONTEND_USER_CAPS, ['read', 'customer']);

        // Get all the capabilities the user actually have.
        $user_caps = \array_keys(\array_filter($user->allcaps));

        return apply_filters(
            Hooks::FILTER_IS_FRONTEND_USER,
            \array_diff($user_caps, $frontend_user_caps) === [], // User should only have whitelisted capabilities.
            $user
        );
    }
}

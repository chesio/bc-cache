<?php

declare(strict_types=1);

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
     * Retrieve MIME Type of HTTP response from response headers.
     *
     * @param string[] $response_headers
     *
     * @return string MIME Type of HTTP response or empty string if no Content-Type header has been found in PHP response headers.
     */
    public static function getResponseMimeType($response_headers): string
    {
        $mime_type = '';

        foreach ($response_headers as $response_header) {
            $matches = [];

            if (\preg_match('/Content-Type: ?(\S+\/\S+); ?charset=\S+/i', $response_header, $matches)) {
                $mime_type = $matches[1];
            }
        }

        return $mime_type;
    }


    /**
     * Remove all but $allowed_header_types from list of HTTP $headers.
     *
     * @param string[] $headers List of headers to filter.
     * @param string[] $allowed_header_types List of allowed header types (names).
     *
     * @return string[] List of $headers including only types included in $allowed_header_types.
     */
    public static function filterHttpHeaders(array $headers, array $allowed_header_types): array
    {
        return \array_filter(
            $headers,
            function (string $header) use ($allowed_header_types): bool {
                foreach ($allowed_header_types as $allowed_header_type) {
                    if (\stripos($header, $allowed_header_type) === 0) { // Perform case-insensitive search!
                        return true;
                    }
                }

                return false;
            }
        );
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
        $user ??= wp_get_current_user();

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

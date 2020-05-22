<?php

namespace BlueChip\Cache;

abstract class Hooks
{
    /**
     * @var string Name of action that triggers cache flush.
     */
    public const ACTION_FLUSH_CACHE = 'bc-cache/action:flush-cache';

    /**
     * @var string Name of hook to filter whether cache locking should be disabled.
     */
    public const FILTER_DISABLE_CACHE_LOCKING = 'bc-cache/filter:disable-cache-locking';

    /**
     * @var string Name of hook to filter result of can user flush cache check.
     */
    public const FILTER_USER_CAN_FLUSH_CACHE = 'bc-cache/filter:can-user-flush-cache';

    /**
     * @var string Name of hook to filter result of skip cache check.
     */
    public const FILTER_SKIP_CACHE = 'bc-cache/filter:skip-cache';

    /**
     * @var string Name of hook to filter list of actions that trigger cache flushing.
     */
    public const FILTER_FLUSH_HOOKS = 'bc-cache/filter:flush-hooks';

    /**
     * @var string Name of hook to filter HTML signature appended to cached data.
     */
    public const FILTER_HTML_SIGNATURE = 'bc-cache/filter:html-signature';

    /**
     * @var string Name of hook to filter the only capabilities a front-end user can have.
     */
    public const FILTER_FRONTEND_USER_CAPS = 'bc-cache/filter:frontend-user-capabilities';

    /**
     * @var string Name of hook to filter whether given user is a front-end user.
     */
    public const FILTER_IS_FRONTEND_USER = 'bc-cache/filter:is-frontend-user';

    /**
     * @var string Name of hook to filter name of front-end user cookie.
     */
    public const FILTER_FRONTEND_USER_COOKIE_NAME = 'bc-cache/filter:frontend-user-cookie-name';

    /**
     * @var string Name of hook to filter contents of front-end user cookie.
     */
    public const FILTER_FRONTEND_USER_COOKIE_VALUE = 'bc-cache/filter:frontend-user-cookie-value';

    /**
     * @var string Name of hook to filter whether post type is deemed as public or not.
     */
    public const FILTER_IS_PUBLIC_POST_TYPE = 'bc-cache/filter:is-public-post-type';

    /**
     * @var string Name of hook to filter current HTTP request variant.
     */
    public const FILTER_REQUEST_VARIANT = 'bc-cache/filter:request-variant';

    /**
     * @var string Name of hook to filter all configured HTTP request variants.
     */
    public const FILTER_REQUEST_VARIANTS = 'bc-cache/filter:request-variants';

    /**
     * @var string Name of hook to filter list of whitelisted query string arguments.
     */
    public const FILTER_WHITELISTED_QUERY_STRING_FIELDS = 'bc-cache/filter:query-string-fields-whitelist';
}

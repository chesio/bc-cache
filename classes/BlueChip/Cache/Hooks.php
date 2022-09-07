<?php

namespace BlueChip\Cache;

abstract class Hooks
{
    /**
     * @var string Name of action that is triggered after cache has been flushed.
     */
    public const ACTION_CACHE_FLUSHED = 'bc-cache/action:cache-flushed';

    /**
     * @var string Name of action that triggers cache flush.
     */
    public const ACTION_FLUSH_CACHE = 'bc-cache/action:flush-cache';

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
     * @var string Name of hook to filter whether post type is deemed as public or not.
     */
    public const FILTER_IS_PUBLIC_TAXONOMY = 'bc-cache/filter:is-public-taxonomy';

    /**
     * @var string Name of hook to filter current HTTP request variant.
     */
    public const FILTER_REQUEST_VARIANT = 'bc-cache/filter:request-variant';

    /**
     * @var string Name of hook to filter all configured HTTP request variants.
     */
    public const FILTER_REQUEST_VARIANTS = 'bc-cache/filter:request-variants';

    /**
     * @var string Name of hook to filter format of cache generation timestamp. Must be a valid format for wp_date().
     */
    public const FILTER_CACHE_GENERATION_TIMESTAMP_FORMAT = 'bc-cache/filter:cache-generation-timestamp-format';

    /**
     * @var string Name of hook to filter type of HTTP headers saved together with cache entry.
     */
    public const FILTER_CACHED_RESPONSE_HEADERS = 'bc-cache/filter:cached-response-headers';

    /**
     * @var string Name of hook to filter arguments of HTTP request run during cache warm up.
     */
    public const FILTER_CACHE_WARM_UP_REQUEST_ARGS = 'bc-cache/filter:cache-warm-up-request-arguments';

    /**
     * @var string Name of hook to filter the amount of time between cache flush and cache warm up invocation.
     */
    public const FILTER_CACHE_WARM_UP_INVOCATION_DELAY = 'bc-cache/filter:cache-warm-up-invocation-delay';

    /**
     * @var string Name of hook to filter time a single warm up run can take.
     */
    public const FILTER_CACHE_WARM_UP_RUN_TIMEOUT = 'bc-cache/filter:cache-warm-up-run-timeout';

    /**
     * @var string Name of hook to filter initial list of URLs to be processed to cache warm up.
     */
    public const FILTER_CACHE_WARM_UP_INITIAL_URL_LIST = 'bc-cache/filter:cache-warm-up-initial-url-list';

    /**
     * @var string Name of hook to filter final list of URLs to be processed in cache warm up.
     */
    public const FILTER_CACHE_WARM_UP_FINAL_URL_LIST = 'bc-cache/filter:cache-warm-up-final-url-list';

    /**
     * @var string Name of hook to filter list of whitelisted query string arguments.
     */
    public const FILTER_WHITELISTED_QUERY_STRING_FIELDS = 'bc-cache/filter:query-string-fields-whitelist';
}

<?php
/**
 * @package BC_Cache
 */

namespace BlueChip\Cache;

abstract class Hooks
{
    /**
     * @var string Name of action that triggers cache flush.
     */
    const ACTION_FLUSH_CACHE = 'bc-cache/action:flush-cache';

    /**
     * @var string Name of hook to filter whether cache locking should be enabled.
     */
    const FILTER_DISABLE_CACHE_LOCKING = 'bc-cache/filter:disable-cache-locking';

    /**
     * @var string Name of hook to filter result of can user flush cache check.
     */
    const FILTER_USER_CAN_FLUSH_CACHE = 'bc-cache/filter:can-user-flush-cache';

    /**
     * @var string Name of hook to filter result of skip cache check.
     */
    const FILTER_SKIP_CACHE = 'bc-cache/filter:skip-cache';

    /**
     * @var string Name of hook to filter list of actions that trigger cache flushing.
     */
    const FILTER_FLUSH_HOOKS = 'bc-cache/filter:flush-hooks';

    /**
     * @var string Name of hook to filter HTML signature appended to cached data.
     */
    const FILTER_HTML_SIGNATURE = 'bc-cache/filter:html-signature';

    /**
     * @var string Name of hook to filter current HTTP request variant.
     */
    const FILTER_REQUEST_VARIANT = 'bc-cache/filter:request-variant';

    /**
     * @var string Name of hook to filter all configured HTTP request variants.
     */
    const FILTER_REQUEST_VARIANTS = 'bc-cache/filter:request-variants';

    /**
     * @var string Name of hook to filter list of whitelisted query string arguments.
     */
    const FILTER_WHITELISTED_QUERY_STRING_FIELDS = 'bc-cache/filter:query-string-fields-whitelist';
}

<?php
/**
 * @package BC_Cache
 */

namespace BlueChip\Cache;

abstract class Hooks
{
    /**
     * Name of action that triggers cache flush.
     */
    const ACTION_FLUSH_CACHE = 'bc-cache/action:flush-cache';

    /**
     * Name of hook to filter result of can user flush cache check.
     */
    const FILTER_USER_CAN_FLUSH_CACHE = 'bc-cache/filter:can-user-flush-cache';

    /**
     * Name of hook to filter result of skip cache check.
     */
    const FILTER_SKIP_CACHE = 'bc-cache/filter:skip-cache';

    /**
     * Name of hook to filter list of actions that trigger cache flushing.
     */
    const FILTER_FLUSH_HOOKS = 'bc-cache/filter:flush-hooks';

    /**
     * Name of hook to filter HTML signature appended to cached data.
     */
    const FILTER_HTML_SIGNATURE = 'bc-cache/filter:html-signature';
}

<?php

declare(strict_types=1);

namespace BlueChip\Cache;

abstract class ThemeFeatures
{
    /**
     * @var string Name of theme feature to activate generation and delivery of cached content to front-end users.
     */
    public const CACHING_FOR_FRONTEND_USERS = 'caching-for-frontend-users';
}

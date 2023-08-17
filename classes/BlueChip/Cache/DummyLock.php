<?php

declare(strict_types=1);

namespace BlueChip\Cache;

/**
 * Dummy lock does no locking at all, just pretends to do so.
 */
class DummyLock implements Lock
{
    public function setUp(): bool
    {
        return true;
    }

    public function tearDown(): bool
    {
        return true;
    }

    public function acquire(bool $exclusive, bool $non_blocking = false): bool
    {
        // Always suceeeds.
        return true;
    }

    public function release(): bool
    {
        // Always suceeeds.
        return true;
    }
}

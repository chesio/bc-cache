<?php

namespace BlueChip\Cache;

interface Lock
{
    public function setUp(): bool;

    public function tearDown(): bool;

    public function acquire(bool $exclusive, bool $non_blocking = false): bool;

    public function release(): bool;
}

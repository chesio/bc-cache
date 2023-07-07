<?php

declare(strict_types=1);

namespace BlueChip\Cache;

/**
 * Serialization helper
 */
abstract class Serializable
{
    /**
     * @var int
     *
     * @internal Should be overriden in child classes to non-zero value and incremented on every class properties change.
     */
    protected const DB_VERSION = 0;

    public function __serialize(): array
    {
        return ['db_version' => static::DB_VERSION, 'data' => $this->deflate()];
    }

    public function __unserialize(array $data): void
    {
        if ($data['db_version'] === static::DB_VERSION) {
            $this->inflate($data['data']);
        }
    }

    abstract protected function deflate(): array;

    abstract protected function inflate(array $data);
}

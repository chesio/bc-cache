<?php

namespace BlueChip\Cache;

/**
 * Serialization helper
 *
 * @internal As soon as PHP 7.4 is required, only __serialize() and __unserialize() methods are necessary.
 */
abstract class Serializable implements \Serializable
{
    /**
     * @var int
     *
     * @internal Should be overriden in child classes to non-zero value and incremented on every class properties change.
     */
    protected const DB_VERSION = 0;


    public function serialize(): string
    {
        return \serialize($this->__serialize());
    }


    public function unserialize($serialized): void
    {
        $this->__unserialize(\unserialize($serialized));
    }


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

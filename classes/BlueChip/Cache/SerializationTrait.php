<?php

declare(strict_types=1);

namespace BlueChip\Cache;

/**
 * Serialization helper that provides methods to add (when serializing) and verify (when deserializing)a database (or serial) version number.
 */
trait SerializationTrait
{
    /**
     * @param array<string,mixed> $data Data to serialize with given $db_version
     * @param int $db_version Database (serial) version to store along $data
     *
     * @return array{db_version:int,data:array<string,mixed>} Data and database version store in array together ready for serialization
     */
    private function serialize(array $data, int $db_version): array
    {
        return ['db_version' => $db_version, 'data' => $data];
    }

    /**
     * @param array{db_version:int,data:array<string,mixed>} $data Data to unserialize
     * @param int $db_version Database (serial) version to check
     *
     * @return array<string,mixed> Actual data or empty array if database version did not match
     */
    private function unserialize(array $data, int $db_version): array
    {
        if ($data['db_version'] !== $db_version) {
            throw new Exception(
                sprintf('Database version %d does not match required database version %d when unserializing instance of class %s!', $data['db_version'], $db_version, __CLASS__)
            );
        }

        return $data['data'];
    }
}

<?php

declare(strict_types=1);

namespace BlueChip\Cache;

/**
 * A single cache item consists of URL and request variant.
 */
readonly class Item
{
    use SerializationTrait;

    /**
     * @var int Internal class version (used for serialization/unserialization)
     */
    protected const DB_VERSION = 1;

    /**
     * @var string
     */
    private const SEPARATOR = '#';


    public function __construct(protected string $url, protected string $request_variant)
    {
    }


    public function __serialize(): array
    {
        return $this->serialize(
            ['request_variant' => $this->request_variant, 'url' => $this->url],
            self::DB_VERSION,
        );
    }


    /**
     * @param array{db_version:int,data:array<string,mixed>} $data
     */
    public function __unserialize(array $data): void
    {
        ['request_variant' => $this->request_variant, 'url' => $this->url] = $this->unserialize($data, self::DB_VERSION);
    }


    public function getUrl(): string
    {
        return $this->url;
    }


    public function getRequestVariant(): string
    {
        return $this->request_variant;
    }


    public function __toString(): string
    {
        return $this->url . self::SEPARATOR . $this->request_variant;
    }


    public static function createFromString(string $value): self
    {
        [$url, $request_variant] = \explode(self::SEPARATOR, $value);

        return new self($url, $request_variant);
    }
}

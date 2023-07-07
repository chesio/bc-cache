<?php

namespace BlueChip\Cache;

/**
 * A single cache item consists of URL and request variant.
 */
class Item extends Serializable
{
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


    /**
     * @internal Serialization helper.
     */
    protected function deflate(): array
    {
        return ['request_variant' => $this->request_variant, 'url' => $this->url];
    }


    /**
     * @internal Serialization helper.
     */
    public function inflate(array $data): void
    {
        ['request_variant' => $this->request_variant, 'url' => $this->url] = $data;
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

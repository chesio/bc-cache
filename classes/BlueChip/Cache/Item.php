<?php

namespace BlueChip\Cache;

/**
 * A single cache item consists of URL and request variant.
 */
class Item
{
    /**
     * @var string
     */
    private const SEPARATOR = '#';

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $request_variant;


    public function __construct(string $url, string $request_variant)
    {
        $this->url = $url;
        $this->request_variant = $request_variant;
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

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
}

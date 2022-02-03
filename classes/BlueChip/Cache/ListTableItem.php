<?php

namespace BlueChip\Cache;

/**
 * A single cache item with additional data for list table view.
 */
class ListTableItem extends Item
{
    /**
     * @var string
     */
    private $entry_id;

    /**
     * @var int|null
     */
    private $timestamp;

    /**
     * @var int
     */
    private $html_file_size;

    /**
     * @var int
     */
    private $gzip_file_size;


    public function __construct(string $entry_id, string $url, string $request_variant, ?int $timestamp, int $html_file_size, int $gzip_file_size)
    {
        parent::__construct($url, $request_variant);

        $this->entry_id = $entry_id;
        $this->timestamp = $timestamp;
        $this->html_file_size = $html_file_size;
        $this->gzip_file_size = $gzip_file_size;
    }


    public function getEntryId(): string
    {
        return $this->entry_id;
    }


    public function getGzipFileSize(): int
    {
        return $this->gzip_file_size;
    }


    public function getHtmlFileSize(): int
    {
        return $this->html_file_size;
    }


    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }


    public function getTotalDiskSize(): int
    {
        return $this->gzip_file_size + $this->html_file_size;
    }


    public function getItem(): Item
    {
        return new Item($this->getUrl(), $this->getRequestVariant());
    }
}

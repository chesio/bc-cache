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
    protected $entry_id;

    /**
     * @var int|null
     */
    protected $timestamp;

    /**
     * @var int
     */
    protected $plain_file_size;

    /**
     * @var int
     */
    protected $gzip_file_size;

    /**
     * @var int
     */
    protected $htaccess_file_size;

    /**
     * @var int Sum of plain, GZIP and .htaccess file size
     */
    protected $total_size;


    public function __construct(string $entry_id, string $url, string $request_variant, ?int $timestamp, int $plain_file_size, int $gzip_file_size, int $htaccess_file_size)
    {
        parent::__construct($url, $request_variant);

        $this->entry_id = $entry_id;
        $this->timestamp = $timestamp;
        $this->plain_file_size = $plain_file_size;
        $this->gzip_file_size = $gzip_file_size;
        $this->htaccess_file_size = $htaccess_file_size;
        $this->total_size = $plain_file_size + $gzip_file_size + $htaccess_file_size;
    }


    /**
     * Property getter
     *
     * @param string $name Property name
     *
     * @return mixed Property value or null if property does not exists.
     */
    public function __get(string $name)
    {
        return \property_exists($this, $name) ? $this->$name : null;
    }


    public function getEntryId(): string
    {
        return $this->entry_id;
    }


    public function getGzipFileSize(): int
    {
        return $this->gzip_file_size;
    }


    public function getPlainFileSize(): int
    {
        return $this->plain_file_size;
    }


    public function getHtaccessFileSize(): int
    {
        return $this->htaccess_file_size;
    }


    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }


    public function getTotalSize(): int
    {
        return $this->total_size;
    }


    public function getItem(): Item
    {
        return new Item($this->getUrl(), $this->getRequestVariant());
    }
}

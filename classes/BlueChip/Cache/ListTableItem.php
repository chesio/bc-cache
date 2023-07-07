<?php

namespace BlueChip\Cache;

/**
 * A single cache item with additional data for list table view.
 */
class ListTableItem extends Item
{
    protected string $entry_id;

    protected ?int $timestamp;

    protected int $plain_file_size;

    protected int $gzip_file_size;

    protected int $htaccess_file_size;

    /**
     * @var int Total disk size occupied by list table item
     *
     * @internal Total disk size is not always equal to sum of plain, GZIP and .htaccess file! When multiple request
     * variants are in use, only single ListItemTable instance from all instances with particular $url has size of
     * .htaccess file calculated in its total disk size. This is a necessary workaround to account for the fact that
     * single .htaccess file is shared between all request variants. This way sum over total disk sizes of all
     * ListItemTable instances is technically valid.
     */
    protected int $total_disk_size;

    /**
     * @var int Sum of plain, GZIP and .htaccess file size
     */
    protected int $total_size;


    public function __construct(string $entry_id, string $url, string $request_variant, ?int $timestamp, int $total_disk_size, int $plain_file_size, int $gzip_file_size, int $htaccess_file_size)
    {
        parent::__construct($url, $request_variant);

        $this->entry_id = $entry_id;
        $this->timestamp = $timestamp;
        $this->total_disk_size = $total_disk_size;
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


    /**
     * @return int Total disk size (may differ from total size when multiple request variants are in use).
     */
    public function getTotalDiskSize(): int
    {
        return $this->total_disk_size;
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

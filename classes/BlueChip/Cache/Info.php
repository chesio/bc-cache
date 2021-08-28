<?php

namespace BlueChip\Cache;

class Info
{
    /**
     * @var string
     */
    private const CACHE_AGE_KEY = 'cache-age';

    /**
     * @var string
     */
    private const CACHE_SIZE_KEY = 'cache-size';

    /**
     * @var array
     */
    private const DEFAULT_DATA = [
        self::CACHE_AGE_KEY => null,
        self::CACHE_SIZE_KEY => null,
    ];


    /**
     * @var mixed[] Cache data (lazy loaded)
     */
    private $data = [];

    /**
     * @var bool True if cache info has been changed via any of set*() methods, false otherwise.
     */
    private $is_dirty = false;

    /**
     * @var string Name of transient to store information as.
     */
    private $transient_key;


    /**
     * @param string $transient_key Name of transient to store information as.
     */
    public function __construct(string $transient_key)
    {
        $this->transient_key = $transient_key;
    }


    /**
     * Reset cache info (= cache has just been flushed).
     */
    public function reset(): self
    {
        return $this->resetAge()->resetSize();
    }


    /**
     * @return int|null Time (as Unix timestamp) when the cache has been fully flushed or null if unknown.
     */
    public function getAge(): ?int
    {
        if (!isset($this->data[self::CACHE_AGE_KEY])) {
            $this->read();
        }

        return $this->data[self::CACHE_AGE_KEY];
    }


    /**
     * Set cache modification date to now.
     *
     * @return \BlueChip\Cache\Info
     */
    public function resetAge(): self
    {
        $this->data[self::CACHE_AGE_KEY] = \time();
        $this->is_dirty = true;
        return $this;
    }


    /**
     * @return int|null Cache size or null if size is unknown.
     */
    public function getSize(): ?int
    {
        if (!isset($this->data[self::CACHE_SIZE_KEY])) {
            $this->read();
        }

        return $this->data[self::CACHE_SIZE_KEY];
    }


    /**
     * Decrement cache size by $bytes.
     *
     * @param int $bytes
     * @return \BlueChip\Cache\Info
     */
    public function decrementSize(int $bytes): self
    {
        if (($size = $this->getSize()) !== null) {
            if ($size >= $bytes) {
                // Decrement the size.
                $this->setSize($size - $bytes);
            } else {
                // Size cannot be negative, mark it as corrupted/unknown.
                $this->unsetSize();
            }
        }

        return $this;
    }


    /**
     * Increment cache size by $bytes.
     *
     * @param int $bytes
     * @return \BlueChip\Cache\Info
     */
    public function incrementSize(int $bytes): self
    {
        if (($size = $this->getSize()) !== null) {
            $this->setSize($size + $bytes);
        }

        return $this;
    }


    /**
     * Set cache size to 0.
     *
     * @return \BlueChip\Cache\Info
     */
    public function resetSize(): self
    {
        return $this->setSize(0);
    }


    /**
     * Set cache size to $size.
     *
     * @param int $size New cache size (must be greater or equal to 0).
     * @return \BlueChip\Cache\Info
     */
    public function setSize(int $size): self
    {
        if ($size >= 0) {
            $this->data[self::CACHE_SIZE_KEY] = $size;
            $this->is_dirty = true;
        }
        return $this;
    }


    /**
     * Mark cache size as unknown.
     *
     * @return \BlueChip\Cache\Info
     */
    public function unsetSize(): self
    {
        $this->data[self::CACHE_SIZE_KEY] = null;
        $this->is_dirty = true;
        return $this;
    }


    /**
     * Reset cache information on setup.
     *
     * @return bool True on success, false on failure.
     */
    public function setUp(): bool
    {
        return $this->reset()->write();
    }


    /**
     * Remove cache information from database.
     *
     * @return bool True on success, false on failure.
     */
    public function tearDown(): bool
    {
        $this->data = self::DEFAULT_DATA;
        $this->is_dirty = false; // = it is safe to call write() afterwards

        return \delete_transient($this->transient_key);
    }


    /**
     * Lazy load cache information from database.
     *
     * @internal Care must be taken to not overwrite values already present in data.
     */
    private function read(): void
    {
        // Transient data beat default data, current data beat all.
        $this->data = \array_merge(self::DEFAULT_DATA, \get_transient($this->transient_key) ?: [], $this->data);
    }


    /**
     * Attempt to write cache information to database if it seems to have changed.
     *
     * @return bool True if cache information has been updated in database (or was already up to date), false otherwise.
     */
    public function write(): bool
    {
        if (!$this->is_dirty) {
            // Nothing has changed.
            return true;
        }

        if (\count($this->data) < \count(self::DEFAULT_DATA)) {
            // One or more data pieces are missing, load them from database first.
            $this->read();
        }

        // If transient has been set successfully, cache info is no longer dirty.
        $this->is_dirty = !\set_transient($this->transient_key, $this->data);

        return !$this->is_dirty;
    }
}

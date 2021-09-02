<?php

namespace BlueChip\Cache;

/**
 * Advisory file locking
 *
 * @internal In case of I/O errors, locking fails silently - acquiring and releasing of lock always succeeds immediately.
 *
 * @link https://secure.php.net/manual/en/function.flock.php
 */
class Lock
{
    /**
     * @var string
     */
    private $file_name;

    /**
     * @var resource|bool|null File handle if lock file has been opened successfully, false in case of failure, null if file has not been opened yet.
     */
    private $file_handle;


    /**
     * @param string $file_name Lock file name.
     */
    public function __construct(string $file_name)
    {
        $this->file_name = $file_name;
        $this->file_handle = null;
    }


    /**
     * Attempt to create readable lock file if it does not exist yet.
     *
     * @return bool True on success (lock file exists or has been created and is readable), false otherwise.
     */
    public function setUp(): bool
    {
        if (!\file_exists($this->file_name)) {
            $dirname = \dirname($this->file_name);

            if (!\is_dir($dirname) && !wp_mkdir_p($dirname)) {
                \trigger_error(\sprintf('Failed to create lock file directory %s.', $dirname), E_USER_WARNING);
                return false;
            }

            if (!\touch($this->file_name)) {
                \trigger_error(\sprintf('Failed to create lock file %s.', $this->file_name), E_USER_WARNING);
                return false;
            }
        }

        return \is_readable($this->file_name);
    }


    /**
     * Remove the lock file from file system. Attempt to release the lock first if the file is open.
     *
     * @return bool True on success, false on otherwise.
     */
    public function tearDown(): bool
    {
        if (\file_exists($this->file_name)) {
            // Release the lock (and close the file) if file is open.
            $file_closed = \is_resource($this->file_handle) ? $this->release() : true;

            // Only attempt to remove the file if closed.
            return $file_closed ? \unlink($this->file_name) : false;
        }

        return true;
    }


    /**
     * Acquire the lock.
     *
     * @param bool $exclusive If true, require exclusive lock. If false, require shared lock.
     * @param bool $non_blocking [optional] If true, do not wait for lock, but fail immediately.
     *
     * @return bool True on success, false on failure.
     */
    public function acquire(bool $exclusive, bool $non_blocking = false): bool
    {
        if ($this->file_handle === false) {
            // Do not attempt to open lock file if previous attempt failed => silently pass.
            return true;
        }

        if (!\is_resource($this->file_handle)) {
            // Lock file not opened yet or closed already.

            if (!\file_exists($this->file_name) && !$this->setUp()) {
                // Lock file not available => silently pass.
                return true;
            }

            $this->file_handle = \fopen($this->file_name, 'r+');

            if ($this->file_handle === false) {
                // Failed to open lock file => silently pass.
                return true;
            }
        }

        $operation = $exclusive ? LOCK_EX : LOCK_SH;

        if ($non_blocking) {
            $operation |= LOCK_NB;
        }

        return \flock($this->file_handle, $operation);
    }


    /**
     * Release the lock and close lock file.
     *
     * @return bool True on success, false on failure.
     */
    public function release(): bool
    {
        return \is_resource($this->file_handle)
            ? \flock($this->file_handle, LOCK_UN) && \fclose($this->file_handle)
            : true
        ;
    }
}

<?php

declare(strict_types=1);

namespace Foxws\Streamer\Filesystem;

use Illuminate\Filesystem\Filesystem;

class TemporaryDirectories
{
    /**
     * Root of the temporary directories.
     */
    protected string $root;

    /**
     * Root of the cache temporary directories (e.g., RAM disk like /dev/shm).
     */
    protected ?string $cacheRoot = null;

    /**
     * Array of all directories
     */
    protected array $directories = [];

    /**
     * Sets the root and removes the trailing slash.
     */
    public function __construct(string $root, ?string $cacheRoot = null)
    {
        $this->root = rtrim($root, '/');
        $this->cacheRoot = $cacheRoot ? rtrim($cacheRoot, '/') : null;
    }

    /**
     * Returns the full path a of new temporary directory.
     */
    public function create(): string
    {
        $directory = $this->root.'/'.bin2hex(random_bytes(8));

        mkdir($directory, 0777, true);

        return $this->directories[] = $directory;
    }

    /**
     * Returns the full path of a new directory in cache storage.
     * Uses cache storage (e.g., RAM disk) if configured, otherwise falls back to regular temp.
     */
    public function createCache(): string
    {
        $root = $this->cacheRoot ?? $this->root;
        $directory = $root.'/'.bin2hex(random_bytes(8));

        mkdir($directory, 0777, true);

        return $this->directories[] = $directory;
    }

    /**
     * Check if cache temporary storage is available.
     */
    public function hasCacheStorage(): bool
    {
        return $this->cacheRoot !== null;
    }

    /**
     * Loop through all directories and delete them.
     */
    public function deleteAll(): void
    {
        $filesystem = new Filesystem;

        foreach ($this->directories as $directory) {
            $filesystem->deleteDirectory($directory);
        }

        $this->directories = [];
    }
}

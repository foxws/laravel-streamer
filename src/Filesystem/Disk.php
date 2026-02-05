<?php

declare(strict_types=1);

namespace Foxws\Streamer\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\ForwardsCalls;
use League\Flysystem\FilesystemAdapter as FlysystemFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * @method bool has(string $path)
 * @method bool exists(string $path)
 * @method string|null get(string $path)
 * @method bool put(string $path, string|resource $contents, mixed $options = [])
 * @method resource|null readStream(string $path)
 * @method bool writeStream(string $path, resource $resource, array $options = [])
 * @method bool makeDirectory(string $path)
 * @method bool setVisibility(string $path, string $visibility)
 * @method array allFiles(string|null $directory = null)
 */
class Disk
{
    use ForwardsCalls;

    protected Filesystem|string $disk;

    protected ?string $temporaryDirectory = null;

    protected ?FilesystemAdapter $filesystemAdapter = null;

    public function __construct(Filesystem|string $disk)
    {
        $this->disk = $disk;
    }

    public static function make(mixed $disk): self
    {
        if ($disk instanceof self) {
            return $disk;
        }

        return new self($disk);
    }

    public static function makeTemporaryDisk(): self
    {
        $filesystemAdapter = app('filesystem')->createLocalDriver([
            'root' => app(TemporaryDirectories::class)->create(),
        ]);

        return new self($filesystemAdapter);
    }

    /**
     * Creates a fresh instance, mostly used to force a new TemporaryDirectory.
     */
    public function clone(): self
    {
        return new Disk($this->disk);
    }

    /**
     * Creates a new TemporaryDirectory instance if none is set, otherwise
     * it returns the current one.
     */
    public function getTemporaryDirectory(): string
    {
        if ($this->temporaryDirectory) {
            return $this->temporaryDirectory;
        }

        return $this->temporaryDirectory = app(TemporaryDirectories::class)->create();
    }

    public function makeMedia(string $path): Media
    {
        return Media::make($this, $path);
    }

    /**
     * Returns the name of the disk. It generates a name if the disk
     * is an instance of Flysystem.
     */
    public function getName(): string
    {
        if (is_string($this->disk)) {
            return $this->disk;
        }

        return get_class($this->getFlysystemAdapter()).'_'.md5((string) spl_object_id($this->getFlysystemAdapter()));
    }

    /**
     * @return FilesystemAdapter
     */
    public function getFilesystemAdapter(): Filesystem|FilesystemAdapter
    {
        if ($this->filesystemAdapter) {
            return $this->filesystemAdapter;
        }

        if ($this->disk instanceof Filesystem) {
            /** @var FilesystemAdapter $adapter */
            $adapter = $this->disk;

            return $this->filesystemAdapter = $adapter;
        }

        return $this->filesystemAdapter = Storage::disk($this->disk);
    }

    /**
     * @phpstan-return FlysystemFilesystemAdapter
     */
    private function getFlysystemAdapter(): FlysystemFilesystemAdapter
    {
        return $this->getFilesystemAdapter()->getAdapter();
    }

    public function isLocalDisk(): bool
    {
        return $this->getFlysystemAdapter() instanceof LocalFilesystemAdapter;
    }

    /**
     * Replaces backward slashes into forward slashes.
     */
    public static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Get the full path for the file at the given "short" path.
     */
    public function path(string $path): string
    {
        $path = $this->getFilesystemAdapter()->path($path);

        return $this->isLocalDisk() ? static::normalizePath($path) : $path;
    }

    /**
     * Forwards all calls to Laravel's FilesystemAdapter which will pass
     * dynamic methods call onto Flysystem.
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->getFilesystemAdapter(), $method, $parameters);
    }
}

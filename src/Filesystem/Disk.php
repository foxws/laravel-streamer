<?php

declare(strict_types=1);

namespace Foxws\Streamer\Filesystem;

use Aws\S3\S3Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\AwsS3V3Adapter as LaravelS3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\ForwardsCalls;
use League\Flysystem\FilesystemAdapter as FlysystemFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;
use ReflectionProperty;

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

    protected ?PathPrefixer $s3PathPrefixer = null;

    protected ?array $s3UploadOptions = null;

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

    public function isS3Disk(): bool
    {
        return $this->getFilesystemAdapter() instanceof LaravelS3Adapter;
    }

    /**
     * Returns the underlying AWS S3 client for this disk.
     * Only valid when isS3Disk() is true.
     */
    public function getS3Client(): S3Client
    {
        /** @var LaravelS3Adapter $adapter */
        $adapter = $this->getFilesystemAdapter();

        return $adapter->getClient();
    }

    /**
     * Returns the S3 bucket name extracted from the Flysystem adapter.
     * Only valid when isS3Disk() is true.
     */
    public function getS3Bucket(): string
    {
        $prop = new ReflectionProperty($this->getFlysystemAdapter(), 'bucket');

        return (string) $prop->getValue($this->getFlysystemAdapter());
    }

    /**
     * Applies the Flysystem path prefix (if any) to a relative path,
     * producing the exact S3 object key that Flysystem would use.
     *
     * Called once per uploaded file, so the reflected prefixer is cached
     * to avoid re-reflecting the adapter for every file in the batch.
     */
    public function prefixS3Path(string $path): string
    {
        return $this->getS3PathPrefixer()->prefixPath($path);
    }

    protected function getS3PathPrefixer(): PathPrefixer
    {
        if ($this->s3PathPrefixer) {
            return $this->s3PathPrefixer;
        }

        $prop = new ReflectionProperty($this->getFlysystemAdapter(), 'prefixer');

        return $this->s3PathPrefixer = $prop->getValue($this->getFlysystemAdapter());
    }

    /**
     * Returns adapter-level default options (e.g. CacheControl) that
     * should be merged into every PutObject call to preserve disk config.
     *
     * @return array<string, mixed>
     */
    public function getS3UploadOptions(): array
    {
        if ($this->s3UploadOptions !== null) {
            return $this->s3UploadOptions;
        }

        $prop = new ReflectionProperty($this->getFlysystemAdapter(), 'options');

        return $this->s3UploadOptions = (array) $prop->getValue($this->getFlysystemAdapter());
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
     * Build a new filesystem instance with the given configuration.
     */
    public function buildFilesystem(array $config): Filesystem
    {
        return Storage::build($config);
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

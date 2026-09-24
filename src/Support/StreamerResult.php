<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Aws\CommandInterface;
use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\S3ClientInterface;
use Foxws\Streamer\Filesystem\Disk;
use Generator;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\EachPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use RuntimeException;
use Throwable;

class StreamerResult
{
    /** @var array<int, CopyFailure> */
    protected array $failedFiles = [];

    protected ?Filesystem $tempFilesystem = null;

    protected ?Filesystem $cacheFilesystem = null;

    public function __construct(
        protected string $output,
        protected ?Disk $sourceDisk = null,
        protected ?string $temporaryDirectory = null,
        protected ?string $cacheDirectory = null,
        protected ?array $configuration = null,
    ) {}

    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Copy exported files from temporary directory to target disk.
     */
    public function toDisk(Disk|Filesystem|string $disk, ?string $visibility = null, bool $cleanup = true, ?string $outputPath = null): self
    {
        $targetDisk = Disk::make($disk);

        if (! $this->temporaryDirectory) {
            throw new RuntimeException('Cannot copy files: temporary directory not set');
        }

        $targetDirectory = $outputPath ?: $this->getSourceDirectory();

        $tempDisk = $this->getTempFilesystem();
        $cacheDisk = $this->getCacheFilesystem();

        $fileOps = array_merge(
            $tempDisk ? $this->buildFileOperations($tempDisk->allFiles(), $targetDirectory, $this->temporaryDirectory) : [],
            $cacheDisk ? $this->buildFileOperations($cacheDisk->allFiles(), $targetDirectory, $this->cacheDirectory) : [],
        );

        throw_if(
            blank($fileOps),
            RuntimeException::class,
            'Streamer produced no output files. Verify that the input media contains valid video or audio streams.'
        );

        $this->copyFilesConcurrently($fileOps, $targetDisk, $visibility, move: $cleanup);

        if ($cleanup) {
            if ($tempDisk && is_dir($this->temporaryDirectory)) {
                $tempDisk->deleteDirectory('/');
                @rmdir($this->temporaryDirectory);
            }

            if ($cacheDisk && $this->cacheDirectory && is_dir($this->cacheDirectory)) {
                $cacheDisk->deleteDirectory('/');
                @rmdir($this->cacheDirectory);
            }
        }

        if ($this->hasCopyFailures()) {
            $errors = array_map(strval(...), $this->failedFiles);

            throw new RuntimeException(
                sprintf(
                    '%d file(s) failed to copy to disk "%s": %s',
                    count($this->failedFiles),
                    $targetDisk->getName(),
                    implode('; ', $errors)
                )
            );
        }

        return $this;
    }

    /**
     * Build file operation descriptors from a list of relative paths.
     *
     * @param  array<string>  $files
     * @return array<int, FileOperation>
     */
    protected function buildFileOperations(array $files, ?string $targetDirectory, string $sourceBasePath): array
    {
        return array_map(fn (string $relativePath) => new FileOperation(
            absolutePath: $sourceBasePath.DIRECTORY_SEPARATOR.$relativePath,
            targetPath: $targetDirectory ? $targetDirectory.$relativePath : $relativePath,
        ), $files);
    }

    /**
     * Copy files to the target disk: async (multipart for large files) for
     * S3-backed disks, a rename when moving onto a local disk, and a
     * sequential stream copy for anything else.
     *
     * @param  array<int, FileOperation>  $fileOps
     */
    protected function copyFilesConcurrently(array $fileOps, Disk $disk, ?string $visibility, bool $move = false): void
    {
        if ($disk->isS3Disk()) {
            $this->uploadFilesViaS3Async($fileOps, $disk, $visibility);

            return;
        }

        if ($move && $disk->isLocalDisk()) {
            $this->moveFilesLocally($fileOps, $disk, $visibility);

            return;
        }

        $this->uploadFilesSequentially($fileOps, $disk, $visibility);
    }

    /**
     * Upload files concurrently to S3 using the AWS SDK's async operations.
     *
     * Dispatches up to `streamer.concurrency_workers` uploads at a time
     * via Guzzle promises. This is I/O-overlap concurrency within a single
     * process — no forking, no process spawning, no shared-state corruption.
     *
     * Files at or above `streamer.multipart_threshold` are sent as a
     * multipart upload with parallel parts, which is faster for large
     * single-file outputs and required for objects over 5 GB.
     *
     * Adapter-level options (e.g. CacheControl) and the Flysystem path prefix
     * are preserved so behaviour matches what writeStream would produce.
     *
     * @param  array<int, FileOperation>  $fileOps
     */
    protected function uploadFilesViaS3Async(array $fileOps, Disk $disk, ?string $visibility): void
    {
        $client = $disk->getS3Client();
        $bucket = $disk->getS3Bucket();
        $adapterOptions = $disk->getS3UploadOptions();
        $concurrency = (int) ($this->configuration['concurrency_workers'] ?? 10);
        $multipartThreshold = (int) ($this->configuration['multipart_threshold'] ?? 64 * 1024 * 1024);
        $multipartOptions = [
            'part_size' => (int) ($this->configuration['multipart_part_size'] ?? 16 * 1024 * 1024),
            'concurrency' => (int) ($this->configuration['multipart_concurrency'] ?? 5),
        ];

        $acl = match ($visibility) {
            'public' => 'public-read',
            'private' => 'private',
            default => null,
        };

        $failed = [];

        $generator = (function () use ($fileOps, $client, $bucket, $disk, $adapterOptions, $acl, $multipartThreshold, $multipartOptions, &$failed): Generator {
            foreach ($fileOps as $op) {
                $stream = fopen($op->absolutePath, 'rb');

                if ($stream === false) {
                    $failed[] = new CopyFailure(
                        source: $op->absolutePath,
                        target: $op->targetPath,
                        error: "Failed to open file: {$op->absolutePath}",
                    );

                    continue;
                }

                $key = $disk->prefixS3Path($op->targetPath);

                $objectParams = array_merge($adapterOptions, [
                    'ContentType' => $this->detectContentType($key),
                ]);

                $size = filesize($op->absolutePath);

                $promise = $size !== false && $size >= $multipartThreshold
                    ? $this->multipartUploadAsync($client, $stream, $bucket, $key, $acl, $objectParams, $multipartOptions)
                    : $client->putObjectAsync(array_merge($objectParams, [
                        'Bucket' => $bucket,
                        'Key' => $key,
                        'Body' => $stream,
                    ], $acl !== null ? ['ACL' => $acl] : []));

                yield $promise->then(
                    function () use ($stream): void {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    },
                    function ($reason) use ($stream, $op, &$failed): void {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        $failed[] = new CopyFailure(
                            source: $op->absolutePath,
                            target: $op->targetPath,
                            error: $reason instanceof Throwable ? $reason->getMessage() : (string) $reason,
                        );
                    }
                );
            }
        })();

        (new EachPromise($generator, ['concurrency' => $concurrency]))->promise()->wait();

        $this->failedFiles = array_merge($this->failedFiles, $failed);
    }

    /**
     * Start a multipart upload, aborting it on failure so orphaned parts
     * don't keep taking up (billed) storage in the bucket.
     *
     * @param  resource  $stream
     * @param  array<string, mixed>  $objectParams
     * @param  array{part_size: int, concurrency: int}  $multipartOptions
     */
    protected function multipartUploadAsync(S3ClientInterface $client, $stream, string $bucket, string $key, ?string $acl, array $objectParams, array $multipartOptions): PromiseInterface
    {
        $uploader = new MultipartUploader($client, $stream, [
            ...$multipartOptions,
            'bucket' => $bucket,
            'key' => $key,
            'acl' => $acl,
            'before_initiate' => function (CommandInterface $command) use ($objectParams): void {
                foreach ($objectParams as $name => $value) {
                    $command[$name] = $value;
                }
            },
        ]);

        return $uploader->promise()->otherwise(function ($reason) use ($client) {
            if ($reason instanceof MultipartUploadException && filled($reason->getState()->getId()['UploadId'] ?? null)) {
                try {
                    $client->abortMultipartUpload($reason->getState()->getId());
                } catch (Throwable) {
                    // The upload already failed; a lifecycle rule can clean up what's left.
                }
            }

            return Create::rejectionFor($reason);
        });
    }

    /**
     * Content type for an uploaded object. Encryption keys are raw bytes,
     * but the extension map would label `.key` files as Keynote documents.
     */
    protected function detectContentType(string $path): string
    {
        if (pathinfo($path, PATHINFO_EXTENSION) === 'key') {
            return 'application/octet-stream';
        }

        return (new ExtensionMimeTypeDetector)->detectMimeTypeFromPath($path) ?? 'application/octet-stream';
    }

    /**
     * Move files onto a local disk with rename(), which is near-instant on
     * the same filesystem (PHP copies across filesystems itself). Falls
     * back to a stream copy when the rename fails.
     *
     * @param  array<int, FileOperation>  $fileOps
     */
    protected function moveFilesLocally(array $fileOps, Disk $disk, ?string $visibility): void
    {
        foreach ($fileOps as $op) {
            try {
                $directory = dirname($op->targetPath);

                if ($directory !== '.') {
                    $disk->makeDirectory($directory);
                }

                if (! @rename($op->absolutePath, $disk->path($op->targetPath))) {
                    $this->writeFile($op, $disk, $visibility);

                    continue;
                }

                if ($visibility) {
                    $disk->setVisibility($op->targetPath, $visibility);
                }
            } catch (Throwable $e) {
                $this->failedFiles[] = new CopyFailure(
                    source: $op->absolutePath,
                    target: $op->targetPath,
                    error: $e->getMessage(),
                );
            }
        }
    }

    /**
     * Upload files sequentially to the target disk.
     *
     * @param  array<int, FileOperation>  $fileOps
     */
    protected function uploadFilesSequentially(array $fileOps, Disk $disk, ?string $visibility): void
    {
        foreach ($fileOps as $op) {
            try {
                $this->writeFile($op, $disk, $visibility);
            } catch (Throwable $e) {
                $this->failedFiles[] = new CopyFailure(
                    source: $op->absolutePath,
                    target: $op->targetPath,
                    error: $e->getMessage(),
                );
            }
        }
    }

    /**
     * Stream a single file onto the target disk.
     */
    protected function writeFile(FileOperation $op, Disk $disk, ?string $visibility): void
    {
        $stream = fopen($op->absolutePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Failed to open file: {$op->absolutePath}");
        }

        try {
            $disk->writeStream($op->targetPath, $stream, $visibility ? ['visibility' => $visibility] : []);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Get filesystem instance for temporary directory.
     */
    protected function getTempFilesystem(): ?Filesystem
    {
        if (! $this->temporaryDirectory || ! is_dir($this->temporaryDirectory)) {
            return null;
        }

        if (! $this->tempFilesystem) {
            $this->tempFilesystem = Disk::make('local')->buildFilesystem([
                'driver' => 'local',
                'root' => $this->temporaryDirectory,
            ]);
        }

        return $this->tempFilesystem;
    }

    /**
     * Get filesystem instance for cache directory.
     */
    protected function getCacheFilesystem(): ?Filesystem
    {
        if (! $this->cacheDirectory || ! is_dir($this->cacheDirectory)) {
            return null;
        }

        if (! $this->cacheFilesystem) {
            $this->cacheFilesystem = Disk::make('local')->buildFilesystem([
                'driver' => 'local',
                'root' => $this->cacheDirectory,
            ]);
        }

        return $this->cacheFilesystem;
    }

    /**
     * Get the source directory to preserve directory structure.
     */
    protected function getSourceDirectory(): ?string
    {
        if ($this->sourceDisk && method_exists($this->sourceDisk, 'getDirectory')) {
            $directory = $this->sourceDisk->getDirectory();

            if ($directory && $directory !== '.') {
                return rtrim($directory, '/').'/';
            }
        }

        return null;
    }

    /**
     * Get all encryption key files from the temporary directory.
     *
     * Useful when using key rotation to collect all generated keys.
     *
     * @return array<int, EncryptionKeyFile>
     */
    public function getEncryptionKeys(): array
    {
        $keys = [];

        // Check temp directory for keys
        if ($tempDisk = $this->getTempFilesystem()) {
            $keys = array_merge($keys, $this->extractKeysFromDisk($tempDisk, $this->temporaryDirectory));
        }

        // Check cache directory for keys (where rotation keys are stored)
        if ($cacheDisk = $this->getCacheFilesystem()) {
            $keys = array_merge($keys, $this->extractKeysFromDisk($cacheDisk, $this->cacheDirectory));
        }

        return $keys;
    }

    /**
     * Extract encryption keys from a filesystem disk
     *
     * @return array<int, EncryptionKeyFile>
     */
    protected function extractKeysFromDisk(Filesystem $disk, string $basePath): array
    {
        $keys = [];

        foreach ($disk->allFiles() as $relativePath) {
            $filename = basename($relativePath);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $baseWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

            // Look for encryption key files:
            // 1. *.key extension (static keys)
            // 2. Rotation pattern: key_0, key_1, encryption_0 (with or without .key extension)
            $isRotationKey = preg_match('/^[a-zA-Z_-]+_\d+$/', $baseWithoutExt);
            $isKeyFile = $extension === 'key' || $isRotationKey;

            if ($isKeyFile) {
                $keys[] = new EncryptionKeyFile(
                    path: $basePath.'/'.$relativePath,
                    filename: $filename,
                    content: bin2hex($disk->get($relativePath)),
                );
            }
        }

        return $keys;
    }

    /**
     * Get all files that failed to copy during the last toDisk() operation.
     *
     * @return array<int, CopyFailure>
     */
    public function getFailedFiles(): array
    {
        return $this->failedFiles;
    }

    /**
     * Check if any files failed during the last copy operation.
     */
    public function hasCopyFailures(): bool
    {
        return filled($this->failedFiles);
    }
}

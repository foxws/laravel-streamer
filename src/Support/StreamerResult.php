<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Filesystem\Disk;
use Generator;
use GuzzleHttp\Promise\EachPromise;
use Illuminate\Contracts\Filesystem\Filesystem;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use RuntimeException;
use Throwable;

class StreamerResult
{
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

        $this->copyFilesConcurrently($fileOps, $targetDisk->getName(), $visibility);

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
            $errors = array_map(
                fn (array $f) => "{$f['target']}: {$f['error']}",
                $this->failedFiles
            );

            throw new RuntimeException(
                sprintf(
                    '%d file(s) failed to copy to disk "%s" after retries: %s',
                    count($this->failedFiles),
                    $targetDisk->getName(),
                    implode('; ', $errors)
                )
            );
        }

        return $this;
    }

    /**
     * Build primitive file operation descriptors from a list of relative paths.
     *
     * @param  array<string>  $files
     * @return array<int, array{absolutePath: string, targetPath: string}>
     */
    protected function buildFileOperations(array $files, ?string $targetDirectory, string $sourceBasePath): array
    {
        $ops = [];

        foreach ($files as $relativePath) {
            $absolutePath = $sourceBasePath.DIRECTORY_SEPARATOR.$relativePath;

            $ops[] = [
                'absolutePath' => $absolutePath,
                'targetPath' => $targetDirectory ? $targetDirectory.$relativePath : $relativePath,
            ];
        }

        return $ops;
    }

    /**
     * Upload files to the target disk, using async S3 promises when the disk
     * is S3-backed, and a sequential loop for local/other disks.
     *
     * @param  array<int, array{absolutePath: string, targetPath: string}>  $fileOps
     */
    protected function copyFilesConcurrently(array $fileOps, string $diskName, ?string $visibility): void
    {
        $disk = Disk::make($diskName);

        if ($disk->isS3Disk()) {
            $this->uploadFilesViaS3Async($fileOps, $disk, $visibility);

            return;
        }

        $this->uploadFilesSequentially($fileOps, $disk, $visibility);
    }

    /**
     * Upload files concurrently to S3 using the AWS SDK's putObjectAsync.
     *
     * @param  array<int, array{absolutePath: string, targetPath: string}>  $fileOps
     */
    protected function uploadFilesViaS3Async(array $fileOps, Disk $disk, ?string $visibility): void
    {
        $client = $disk->getS3Client();
        $bucket = $disk->getS3Bucket();
        $adapterOptions = $disk->getS3UploadOptions();
        $concurrency = (int) ($this->configuration['concurrency_workers'] ?? 10);
        $mimeDetector = new FinfoMimeTypeDetector;

        $acl = match ($visibility) {
            'public' => 'public-read',
            'private' => 'private',
            default => null,
        };

        $failed = [];

        $generator = (function () use ($fileOps, $client, $bucket, $disk, $adapterOptions, $acl, $mimeDetector, &$failed): Generator {
            foreach ($fileOps as $op) {
                $stream = fopen($op['absolutePath'], 'rb');

                if ($stream === false) {
                    $failed[] = [
                        'source' => $op['absolutePath'],
                        'target' => $op['targetPath'],
                        'error' => "Failed to open file: {$op['absolutePath']}",
                    ];

                    continue;
                }

                $key = $disk->prefixS3Path($op['targetPath']);

                $params = array_merge($adapterOptions, [
                    'Bucket' => $bucket,
                    'Key' => $key,
                    'Body' => $stream,
                    'ContentType' => $mimeDetector->detectMimeTypeFromPath($key) ?? 'application/octet-stream',
                ]);

                if ($acl !== null) {
                    $params['ACL'] = $acl;
                }

                yield $client->putObjectAsync($params)->then(
                    function () use ($stream): void {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    },
                    function ($reason) use ($stream, $op, &$failed): void {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        $failed[] = [
                            'source' => $op['absolutePath'],
                            'target' => $op['targetPath'],
                            'error' => $reason instanceof Throwable ? $reason->getMessage() : (string) $reason,
                        ];
                    }
                );
            }
        })();

        (new EachPromise($generator, ['concurrency' => $concurrency]))->promise()->wait();

        $this->failedFiles = array_merge($this->failedFiles, $failed);
    }

    /**
     * Upload files sequentially to the target disk.
     *
     * @param  array<int, array{absolutePath: string, targetPath: string}>  $fileOps
     */
    protected function uploadFilesSequentially(array $fileOps, Disk $disk, ?string $visibility): void
    {
        $options = $visibility ? ['visibility' => $visibility] : [];

        foreach ($fileOps as $op) {
            try {
                $stream = fopen($op['absolutePath'], 'rb');

                if ($stream === false) {
                    throw new RuntimeException("Failed to open file: {$op['absolutePath']}");
                }

                $disk->writeStream($op['targetPath'], $stream, $options);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            } catch (Throwable $e) {
                if (isset($stream) && is_resource($stream)) {
                    fclose($stream);
                }

                $this->failedFiles[] = [
                    'source' => $op['absolutePath'],
                    'target' => $op['targetPath'],
                    'error' => $e->getMessage(),
                ];
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
     * @return array<int, array{path: string, filename: string, content: string}> Array of key files with path, filename, and hex-encoded content
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
                $keys[] = [
                    'path' => $basePath.'/'.$relativePath,
                    'filename' => $filename,
                    'content' => bin2hex($disk->get($relativePath)),
                ];
            }
        }

        return $keys;
    }

    /**
     * Get all files that failed to copy during the last toDisk() operation.
     *
     * @return array<int, array{source: string, target: string, error: string}>
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

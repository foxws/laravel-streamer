<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Filesystem\Disk;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Concurrency;

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
     * Copy exported files from temporary directory to target disk
     */
    public function toDisk(Disk|Filesystem|string $disk, ?string $visibility = null, bool $cleanup = true, ?string $outputPath = null): self
    {
        $targetDisk = Disk::make($disk);

        if (! $this->temporaryDirectory) {
            throw new \RuntimeException('Cannot copy files: temporary directory not set');
        }

        // Get the target directory from outputPath parameter or preserve source structure
        $targetDirectory = $outputPath ?: $this->getSourceDirectory();

        $tempDisk = $this->getTempFilesystem();
        $cacheDisk = $this->getCacheFilesystem();

        $fileOps = array_merge(
            $tempDisk ? $this->buildFileOperations($tempDisk->allFiles(), $targetDirectory, $this->temporaryDirectory) : [],
            $cacheDisk ? $this->buildFileOperations($cacheDisk->allFiles(), $targetDirectory, $this->cacheDirectory) : [],
        );

        if (! empty($fileOps)) {
            $this->copyFilesConcurrently($fileOps, $targetDisk->getName(), $visibility);
        }

        // Clean up temporary directories
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
     * Upload files concurrently using the fork driver via the Concurrency facade.
     *
     * Files are chunked into up to CONCURRENCY_WORKERS forks. The fork driver
     * (spatie/fork) has no hard-coded timeout, unlike the default process driver,
     * making it suitable for large file uploads to remote disks such as S3.
     *
     * @param  array<int, array{absolutePath: string, targetPath: string}>  $fileOps
     */
    protected function copyFilesConcurrently(array $fileOps, string $diskName, ?string $visibility): void
    {
        $workers = $this->configuration['concurrency_workers'] ?? 10;
        $chunkSize = (int) ceil(count($fileOps) / $workers);
        $chunks = array_chunk($fileOps, max(1, $chunkSize));

        $tasks = [];

        foreach ($chunks as $chunk) {
            $tasks[] = function () use ($chunk, $diskName, $visibility): array {
                $disk = Disk::make($diskName);
                $failures = [];
                $options = $visibility ? ['visibility' => $visibility] : [];

                foreach ($chunk as $op) {
                    try {
                        $stream = fopen($op['absolutePath'], 'rb');
                        $disk->writeStream($op['targetPath'], $stream, $options);

                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    } catch (\Exception $e) {
                        $failures[] = [
                            'source' => $op['absolutePath'],
                            'target' => $op['targetPath'],
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                return $failures;
            };
        }

        $this->failedFiles = array_merge(...Concurrency::driver('fork')->run($tasks));
    }

    /**     * Get filesystem instance for temporary directory
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
     * Get filesystem instance for cache directory
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

    /**     * Get the source directory to preserve directory structure if no output path specified
     */
    protected function getSourceDirectory(): ?string
    {
        // If we have a source disk with media, preserve its directory structure
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
        return ! empty($this->failedFiles);
    }
}

<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Filesystem\Disk;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;

class StreamerResult
{
    protected array $uploadedEncryptionKeys = [];

    protected array $copiedFiles = [];

    protected array $failedFiles = [];

    protected ?Filesystem $tempFilesystem = null;

    protected ?Filesystem $cacheFilesystem = null;

    public function __construct(
        protected string $output,
        protected ?Disk $sourceDisk = null,
        protected ?string $temporaryDirectory = null,
        protected ?string $cacheDirectory = null
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

        // Copy files from temp directory
        if ($tempDisk = $this->getTempFilesystem()) {
            $this->copyFilesFromDisk($tempDisk, $targetDisk, $targetDirectory, $visibility, $this->temporaryDirectory);
        }

        // Copy files from cache directory if it exists
        if ($cacheDisk = $this->getCacheFilesystem()) {
            $this->copyFilesFromDisk($cacheDisk, $targetDisk, $targetDirectory, $visibility, $this->cacheDirectory);
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
     * Copy files from source disk to target disk
     */
    protected function copyFilesFromDisk(Filesystem $sourceDisk, Disk $targetDisk, ?string $targetDirectory, ?string $visibility, string $sourceBasePath): void
    {
        foreach ($sourceDisk->allFiles() as $relativePath) {
            $targetPath = $targetDirectory ? $targetDirectory.$relativePath : $relativePath;

            // Check if this is an encryption key file
            $filename = basename($relativePath);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $isKeyFile = $extension === 'key' || preg_match('/^[a-zA-Z_-]+_\d+$/', pathinfo($filename, PATHINFO_FILENAME));
            $isSmallFile = $isKeyFile || $extension === 'm3u8';

            try {
                if ($isSmallFile) {
                    $content = $sourceDisk->get($relativePath);
                    $targetDisk->put($targetPath, $content);

                    // Track uploaded encryption key metadata
                    if ($isKeyFile) {
                        $this->uploadedEncryptionKeys[] = [
                            'filename' => $filename,
                            'path' => $targetPath,
                            'content' => bin2hex($content),
                        ];
                    }
                } else {
                    // Stream large binary files (video/audio segments)
                    $stream = $sourceDisk->readStream($relativePath);
                    $targetDisk->writeStream($targetPath, $stream);

                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                if ($visibility) {
                    $targetDisk->setVisibility($targetPath, $visibility);
                }

                // Track successfully copied file
                $this->copiedFiles[$targetPath] = [
                    'source' => $sourceBasePath.'/'.$relativePath,
                    'size' => $sourceDisk->size($relativePath),
                    'type' => $isKeyFile ? 'key' : ($extension === 'm3u8' ? 'manifest' : 'segment'),
                ];
            } catch (\Exception $e) {
                $this->failedFiles[] = [
                    'source' => $sourceBasePath.'/'.$relativePath,
                    'target' => $targetPath,
                    'error' => $e->getMessage(),
                    'size' => 0,
                ];
            }
        }
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
     * Get encryption keys that were uploaded during the last toDisk() call.
     *
     * Returns keys with their uploaded paths and hex-encoded content, ready for database storage.
     *
     * @return array<int, array{filename: string, path: string, content: string}> Array of uploaded keys
     */
    public function getUploadedEncryptionKeys(): array
    {
        return $this->uploadedEncryptionKeys;
    }

    /**
     * Get all successfully copied files from the last toDisk() operation.
     *
     * @return array<string, array{source: string, size: int, type: string}> Array of copied files indexed by target path
     */
    public function getCopiedFiles(): array
    {
        return $this->copiedFiles;
    }

    /**
     * Get all files that failed to copy during the last toDisk() operation.
     *
     * @return array<int, array{source: string, target: string, error: string, size: int}> Array of failed copy operations
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

    /**
     * Get a summary of the copy operation.
     *
     * @return array{total: int, copied: int, failed: int, totalSize: int}
     */
    public function getCopySummary(): array
    {
        $totalSize = 0;
        foreach ($this->copiedFiles as $file) {
            $totalSize += $file['size'] ?? 0;
        }

        return [
            'total' => count($this->copiedFiles) + count($this->failedFiles),
            'copied' => count($this->copiedFiles),
            'failed' => count($this->failedFiles),
            'totalSize' => $totalSize,
        ];
    }
}

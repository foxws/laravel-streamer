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

        // Collect files from temp directory (segments/manifests) and cache directory (encryption keys)
        $files = $this->getAllFilesInTemporaryDirectory($this->temporaryDirectory);

        // Log what files were found in temp directory
        Log::debug('Files found in temporary directory', [
            'temp_directory' => $this->temporaryDirectory,
            'file_count' => count($files),
            'files' => array_map(fn ($f) => [
                'path' => $f,
                'size' => @filesize($f),
            ], $files),
        ]);

        if ($this->cacheDirectory && is_dir($this->cacheDirectory)) {
            $cacheFiles = $this->getAllFilesInTemporaryDirectory($this->cacheDirectory);
            $files = array_merge($files, $cacheFiles);
        }

        // Copy all files to target disk
        foreach ($files as $file) {
            // Determine the relative path from the base directory
            $baseDir = $this->temporaryDirectory;
            if ($this->cacheDirectory && str_starts_with($file, $this->cacheDirectory)) {
                $baseDir = $this->cacheDirectory;
            }

            // Get relative path (preserves subdirectory structure)
            $relativePath = substr($file, strlen($baseDir) + 1);
            $targetPath = $targetDirectory ? $targetDirectory.$relativePath : $relativePath;

            // Check if this is an encryption key file
            $filename = basename($file);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $baseWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $isRotationKey = preg_match('/^[a-zA-Z_-]+_\d+$/', $baseWithoutExt);
            $isKeyFile = $extension === 'key' || $isRotationKey;

            // Small text files (.m3u8 manifests) and key files - use put() for reliability
            $isSmallFile = $isKeyFile || $extension === 'm3u8';

            try {
                $fileSize = filesize($file);

                if ($isSmallFile) {
                    $content = file_get_contents($file);
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
                    $stream = fopen($file, 'rb');
                    try {
                        $targetDisk->writeStream($targetPath, $stream);
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }

                if ($visibility) {
                    $targetDisk->setVisibility($targetPath, $visibility);
                }

                // Track successfully copied file
                $this->copiedFiles[$targetPath] = [
                    'source' => $file,
                    'size' => $fileSize,
                    'type' => $isKeyFile ? 'key' : ($extension === 'm3u8' ? 'manifest' : 'segment'),
                ];

                // Clean up temporary file after copying
                if ($cleanup) {
                    unlink($file);
                }
            } catch (\Exception $e) {
                $this->failedFiles[] = [
                    'source' => $file,
                    'target' => $targetPath,
                    'error' => $e->getMessage(),
                    'size' => $fileSize ?? 0,
                ];
            }

        }

        // Clean up temporary directories if empty (recursively)
        if ($cleanup && is_dir($this->temporaryDirectory)) {
            $this->cleanupDirectory($this->temporaryDirectory);
        }

        if ($cleanup && $this->cacheDirectory && is_dir($this->cacheDirectory)) {
            $this->cleanupDirectory($this->cacheDirectory);
        }

        return $this;
    }

    /**
     * Get all files in the temporary directory (recursively)
     */
    protected function getAllFilesInTemporaryDirectory(string $directory): array
    {
        if (! is_dir($directory)) {
            Log::warning('Temporary directory does not exist', ['directory' => $directory]);

            return [];
        }

        $files = [];
        $items = scandir($directory);

        if ($items === false) {
            Log::error('Failed to scan directory', ['directory' => $directory]);

            return [];
        }

        Log::debug('Scanning directory', [
            'directory' => $directory,
            'items' => array_filter($items, fn ($item) => $item !== '.' && $item !== '..'),
        ]);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_file($path)) {
                $files[] = $path;
            } elseif (is_dir($path)) {
                // Recursively scan subdirectories
                Log::debug('Recursing into subdirectory', ['subdirectory' => $path]);
                $files = array_merge($files, $this->getAllFilesInTemporaryDirectory($path));
            }
        }

        return $files;
    }

    /**
     * Recursively clean up empty directories
     */
    protected function cleanupDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->cleanupDirectory($path);
            }
        }

        // Remove directory if empty
        @rmdir($directory);
    }

    /**
     * Get the source directory to preserve directory structure if no output path specified
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
        if ($this->temporaryDirectory && is_dir($this->temporaryDirectory)) {
            $files = $this->getAllFilesInTemporaryDirectory($this->temporaryDirectory);
            $keys = array_merge($keys, $this->extractKeysFromFiles($files));
        }

        // Check cache directory for keys (where rotation keys are stored)
        if ($this->cacheDirectory && is_dir($this->cacheDirectory)) {
            $cacheFiles = $this->getAllFilesInTemporaryDirectory($this->cacheDirectory);
            $keys = array_merge($keys, $this->extractKeysFromFiles($cacheFiles));
        }

        return $keys;
    }

    /**
     * Extract encryption keys from a list of files
     */
    protected function extractKeysFromFiles(array $files): array
    {
        $keys = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $baseWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

            // Look for encryption key files:
            // 1. *.key extension (static keys)
            // 2. Rotation pattern: key_0, key_1, encryption_0 (with or without .key extension)
            $isRotationKey = preg_match('/^[a-zA-Z_-]+_\d+$/', $baseWithoutExt);
            $isKeyFile = $extension === 'key' || $isRotationKey;

            if ($isKeyFile) {
                $keys[] = [
                    'path' => $file,
                    'filename' => $filename,
                    'content' => bin2hex(file_get_contents($file)),
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

<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Filesystem\Disk;
use Illuminate\Concurrency\ConcurrencyManager;
use Illuminate\Console\Application;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\SerializableClosure\SerializableClosure;

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
     * @return array<int, array{absolutePath: string, targetPath: string, filename: string, extension: string, isKeyFile: bool, isSmallFile: bool, size: int}>
     */
    protected function buildFileOperations(array $files, ?string $targetDirectory, string $sourceBasePath): array
    {
        $ops = [];

        foreach ($files as $relativePath) {
            $filename = basename($relativePath);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $isKeyFile = $extension === 'key' || (bool) preg_match('/^[a-zA-Z_-]+_\d+$/', pathinfo($filename, PATHINFO_FILENAME));
            $absolutePath = $sourceBasePath.DIRECTORY_SEPARATOR.$relativePath;

            $ops[] = [
                'absolutePath' => $absolutePath,
                'targetPath' => $targetDirectory ? $targetDirectory.$relativePath : $relativePath,
                'filename' => $filename,
                'extension' => $extension,
                'isKeyFile' => $isKeyFile,
                'isSmallFile' => $isKeyFile || $extension === 'm3u8',
                'size' => filesize($absolutePath),
            ];
        }

        return $ops;
    }

    /**
     * Upload files concurrently using a direct process pool.
     *
     * We drive the process pool directly (instead of using Concurrency::run()) so we
     * can apply an explicit per-worker timeout from config, overriding Laravel's
     * PendingProcess default of 60 seconds which is far too short for large uploads.
     *
     * @param  array<int, array{absolutePath: string, targetPath: string, filename: string, extension: string, isKeyFile: bool, isSmallFile: bool, size: int}>  $fileOps
     */
    protected function copyFilesConcurrently(array $fileOps, string $diskName, ?string $visibility): void
    {
        $workers = $this->configuration['concurrency_workers'] ?? 10;
        $workerTimeout = $this->configuration['concurrency_timeout'] ?? 3600;
        $chunkSize = (int) ceil(count($fileOps) / $workers);
        $chunks = array_chunk($fileOps, max(1, $chunkSize));

        $tasks = [];

        foreach ($chunks as $chunk) {
            $tasks[] = function () use ($chunk, $diskName, $visibility): array {
                $disk = Storage::disk($diskName);
                $results = [];

                $options = $visibility ? ['visibility' => $visibility] : [];

                foreach ($chunk as $op) {
                    try {
                        $content = null;

                        if ($op['isSmallFile']) {
                            $content = file_get_contents($op['absolutePath']);
                            $disk->put($op['targetPath'], $content, $options);
                        } else {
                            $stream = fopen($op['absolutePath'], 'rb');
                            $disk->writeStream($op['targetPath'], $stream, $options);

                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }

                        $results[] = [
                            'success' => true,
                            'targetPath' => $op['targetPath'],
                            'source' => $op['absolutePath'],
                            'size' => $op['size'],
                            'type' => $op['isKeyFile'] ? 'key' : ($op['extension'] === 'm3u8' ? 'manifest' : 'segment'),
                            'isKeyFile' => $op['isKeyFile'],
                            'filename' => $op['filename'],
                            'keyContent' => $op['isKeyFile'] ? bin2hex($content) : null,
                        ];
                    } catch (\Exception $e) {
                        $results[] = [
                            'success' => false,
                            'targetPath' => $op['targetPath'],
                            'source' => $op['absolutePath'],
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                return $results;
            };
        }

        // Use Concurrency::run() when the facade has been faked/replaced, or when the sync
        // driver is configured (e.g. in tests via TestCase::getEnvironmentSetUp), so tasks
        // run synchronously in-process without spawning subprocesses.
        // In production with a non-sync driver we use Process::pool() directly so we can
        // apply a configurable per-worker timeout (Laravel's default of 60 s is too short).
        if (! (Concurrency::getFacadeRoot() instanceof ConcurrencyManager) || config('concurrency.default') === 'sync') {
            $allChunkResults = collect(Concurrency::run($tasks));
        } else {
            $command = Application::formatCommandString('invoke-serialized-closure');

            $poolResults = Process::pool(function (\Illuminate\Process\Pool $pool) use ($tasks, $command, $workerTimeout): void {
                foreach ($tasks as $key => $task) {
                    $pool->as((string) $key)
                        ->path(base_path())
                        ->timeout($workerTimeout)
                        ->env([
                            'LARAVEL_INVOKABLE_CLOSURE' => base64_encode(
                                serialize(new SerializableClosure($task))
                            ),
                        ])
                        ->command($command);
                }
            })->start()->wait();

            $allChunkResults = $poolResults->collect()->mapWithKeys(function ($result, $key) {
                if ($result->failed()) {
                    throw new \Exception('Concurrent copy process failed with exit code ['.$result->exitCode().']. Message: '.$result->errorOutput());
                }

                $decoded = json_decode($result->output(), true);

                if (! ($decoded['successful'] ?? false)) {
                    throw new \Exception($decoded['message'] ?? 'Concurrent copy process returned an error.');
                }

                return [(int) $key => unserialize($decoded['result'])];
            })->sortKeys()->values();
        }

        foreach ($allChunkResults as $chunkResults) {
            foreach ($chunkResults as $result) {
                if ($result['success']) {
                    $this->copiedFiles[$result['targetPath']] = [
                        'source' => $result['source'],
                        'size' => $result['size'],
                        'type' => $result['type'],
                    ];

                    if ($result['isKeyFile']) {
                        $this->uploadedEncryptionKeys[] = [
                            'filename' => $result['filename'],
                            'path' => $result['targetPath'],
                            'content' => $result['keyContent'],
                        ];
                    }
                } else {
                    $this->failedFiles[] = [
                        'source' => $result['source'],
                        'target' => $result['targetPath'],
                        'error' => $result['error'],
                        'size' => 0,
                    ];
                }
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

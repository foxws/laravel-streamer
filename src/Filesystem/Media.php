<?php

declare(strict_types=1);

namespace Foxws\Streamer\Filesystem;

use Foxws\Streamer\Exceptions\MediaNotFoundException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Config;

class Media
{
    protected Disk $disk;

    protected string $path;

    protected ?string $temporaryDirectory = null;

    protected ?string $genericAlias = null;

    public function __construct(Disk $disk, string $path, bool $createTemp = true)
    {
        $this->disk = $disk;
        $this->path = $path;

        if ($createTemp) {
            $this->makeDirectory();
        }
    }

    /**
     * @param  Disk|Filesystem|string  $disk
     */
    public static function make($disk, string $path, bool $createTemp = true): self
    {
        return new self(Disk::make($disk), $path, $createTemp);
    }

    public function getDisk(): Disk
    {
        return $this->disk;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDirectory(): string
    {
        $directory = rtrim(pathinfo($this->getPath(), PATHINFO_DIRNAME), DIRECTORY_SEPARATOR);

        if ($directory === '.') {
            $directory = '';
        }

        if ($directory) {
            $directory .= DIRECTORY_SEPARATOR;
        }

        return $directory;
    }

    private function makeDirectory(): void
    {
        $disk = $this->getDisk();

        if (! $disk->isLocalDisk()) {
            $disk = $this->temporaryDirectoryDisk();
        }

        $directory = $this->getDirectory();

        if ($disk->has($directory)) {
            return;
        }

        $disk->makeDirectory($directory);
    }

    public function getFilenameWithoutExtension(): string
    {
        return pathinfo($this->getPath())['filename'];
    }

    public function getFilename(): string
    {
        return pathinfo($this->getPath())['basename'];
    }

    private function temporaryDirectoryDisk(): Disk
    {
        return Disk::make($this->temporaryDirectoryAdapter());
    }

    private function temporaryDirectoryAdapter(): FilesystemAdapter
    {
        if (! $this->temporaryDirectory) {
            $this->temporaryDirectory = $this->getDisk()->getTemporaryDirectory();
        }

        /** @var FilesystemAdapter $adapter */
        $adapter = app('filesystem')->createLocalDriver(
            ['root' => $this->temporaryDirectory]
        );

        return $adapter;
    }

    public function getLocalPath(): string
    {
        $disk = $this->getDisk();
        $path = $this->getPath();

        if ($disk->isLocalDisk()) {
            return $disk->path($path);
        }

        $temporaryDirectoryDisk = $this->temporaryDirectoryDisk();

        if ($disk->exists($path) && ! $temporaryDirectoryDisk->exists($path)) {
            $temporaryDirectoryDisk->writeStream($path, $this->readStream($disk, $path));
        }

        return $temporaryDirectoryDisk->path($path);
    }

    /**
     * Get a safe path for Shaka Streamer by creating a generic alias if configured.
     * This prevents issues with special characters in filenames.
     */
    public function getSafeInputPath(): string
    {
        // If force_generic_input is disabled, just return the regular local path
        if (! Config::boolean('streamer.force_generic_input', false)) {
            return $this->getLocalPath();
        }

        // Return cached generic alias if already created
        if ($this->genericAlias) {
            return $this->genericAlias;
        }

        $extension = pathinfo($this->getPath(), PATHINFO_EXTENSION);

        $name = 'input'.($extension ? '.'.$extension : '.tmp');

        $disk = $this->getDisk();
        $temporaryDirectoryDisk = $this->temporaryDirectoryDisk();

        // Link the source to a generic name in the temp directory, and only
        // download it when there is no local copy to link to yet.
        if (! $temporaryDirectoryDisk->exists($name)) {
            $localSourcePath = match (true) {
                $disk->isLocalDisk() => $disk->path($this->getPath()),
                $temporaryDirectoryDisk->exists($this->getPath()) => $temporaryDirectoryDisk->path($this->getPath()),
                default => null,
            };

            $linked = $localSourcePath !== null
                && function_exists('symlink')
                && @symlink($localSourcePath, $temporaryDirectoryDisk->path($name));

            if (! $linked) {
                $temporaryDirectoryDisk->writeStream(
                    $name,
                    $this->readStream($disk, $this->getPath())
                );
            }
        }

        // Cache and return the full absolute path
        $this->genericAlias = $temporaryDirectoryDisk->path($name);

        return $this->genericAlias;
    }

    public function copyAllFromTemporaryDirectory(?string $visibility = null): self
    {
        if (! $this->temporaryDirectory) {
            return $this;
        }

        $temporaryDirectoryDisk = $this->temporaryDirectoryDisk();

        $destinationAdapter = $this->getDisk()->getFilesystemAdapter();

        foreach ($temporaryDirectoryDisk->allFiles() as $path) {
            $destinationAdapter->writeStream($path, $this->readStream($temporaryDirectoryDisk, $path));

            if ($visibility) {
                $destinationAdapter->setVisibility($path, $visibility);
            }
        }

        return $this;
    }

    public function setVisibility(string $path, ?string $visibility = null): self
    {
        $disk = $this->getDisk();

        if ($visibility && $disk->isLocalDisk()) {
            $disk->setVisibility($path, $visibility);
        }

        return $this;
    }

    /**
     * Open a file on a disk for reading, failing clearly when it's gone
     * instead of passing null on to writeStream().
     *
     * @return resource
     */
    protected function readStream(Disk $disk, string $path): mixed
    {
        return $disk->readStream($path)
            ?? throw MediaNotFoundException::unreadable($path);
    }
}

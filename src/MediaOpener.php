<?php

declare(strict_types=1);

namespace Foxws\Streamer;

use Foxws\Streamer\Filesystem\Disk;
use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\Filesystem\MediaCollection;
use Foxws\Streamer\Filesystem\TemporaryDirectories;
use Foxws\Streamer\Support\Packager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaOpener
{
    use ForwardsCalls;

    protected ?Disk $disk = null;

    protected ?Packager $packager = null;

    protected ?MediaCollection $collection = null;

    public function __construct(
        Disk|string|null $disk = null,
        ?Packager $packager = null,
        ?MediaCollection $mediaCollection = null
    ) {
        $this->fromDisk($disk ?: Config::string('filesystems.default'));

        $this->packager = $packager ?: app(Packager::class)->fresh();

        $this->collection = $mediaCollection ?: new MediaCollection;
    }

    public function clone(): self
    {
        return new MediaOpener(
            $this->disk,
            $this->packager,
            $this->collection
        );
    }

    public function fromDisk(Disk|Filesystem|string $disk): self
    {
        $this->disk = Disk::make($disk);

        return $this;
    }

    public function getDisk(): ?Disk
    {
        return $this->disk;
    }

    protected static function makeLocalDiskFromPath(string $path): Disk
    {
        $adapter = (new FilesystemManager(app()))->createLocalDriver([
            'root' => $path,
        ]);

        return Disk::make($adapter);
    }

    /**
     * Instantiates a Media object for each given path.
     */
    public function open($paths): self
    {
        foreach (Arr::wrap($paths) as $path) {
            if ($path instanceof UploadedFile) {
                $disk = static::makeLocalDiskFromPath($path->getPath());

                $media = Media::make($disk, $path->getFilename());
            } else {
                $media = Media::make($this->disk, $path);
            }

            $this->collection->push($media);
        }

        // Initialize the packager with the collection
        $this->packager->open($this->collection);

        return $this;
    }

    /**
     * Open files from a specific disk
     */
    public function openFromDisk(Filesystem|string $disk, $paths): self
    {
        return $this->fromDisk($disk)->open($paths);
    }

    public function get(): MediaCollection
    {
        return $this->collection;
    }

    public function each($items, callable $callback): self
    {
        Collection::make($items)->each(function ($item, $key) use ($callback) {
            return $callback($this->clone(), $item, $key);
        });

        return $this;
    }

    public function getPackager(): Packager
    {
        return $this->packager;
    }

    /**
     * Returns an instance of MediaExporter with the packager.
     */
    public function export(): Exporters\MediaExporter
    {
        return new Exporters\MediaExporter($this->packager);
    }

    /**
     * Create a new DynamicHLSPlaylist instance for customizing HLS playlists.
     */
    public static function dynamicHLSPlaylist(?string $disk = null): Http\DynamicHLSPlaylist
    {
        return new Http\DynamicHLSPlaylist($disk);
    }

    /**
     * Create a new DynamicDASHManifest instance for customizing DASH manifests.
     */
    public static function dynamicDASHManifest(?string $disk = null): Http\DynamicDASHManifest
    {
        return new Http\DynamicDASHManifest($disk);
    }

    public function cleanupTemporaryFiles(): self
    {
        app(TemporaryDirectories::class)->deleteAll();

        return $this;
    }

    public function __call($method, $arguments)
    {
        $result = $this->forwardCallTo($packager = $this->getPackager(), $method, $arguments);

        return ($result === $packager) ? $this : $result;
    }
}

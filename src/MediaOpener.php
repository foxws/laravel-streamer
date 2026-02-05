<?php

declare(strict_types=1);

namespace Foxws\Streamer;

use Foxws\Streamer\Filesystem\Disk;
use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\Filesystem\MediaCollection;
use Foxws\Streamer\Filesystem\TemporaryDirectories;
use Foxws\Streamer\Support\Streamer;
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

    protected ?Streamer $streamer = null;

    protected ?MediaCollection $collection = null;

    public function __construct(
        Disk|string|null $disk = null,
        ?Streamer $streamer = null,
        ?MediaCollection $mediaCollection = null
    ) {
        $this->fromDisk($disk ?: Config::string('filesystems.default'));

        $this->streamer = $streamer ?: app(Streamer::class)->fresh();

        $this->collection = $mediaCollection ?: new MediaCollection;
    }

    public function clone(): self
    {
        return new MediaOpener(
            $this->disk,
            $this->streamer,
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

        // Initialize the streamer with the collection
        $this->streamer->open($this->collection);

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

    public function getStreamer(): Streamer
    {
        return $this->streamer;
    }

    /**
     * Returns an instance of MediaExporter with the streamer.
     */
    public function export(): Exporters\MediaExporter
    {
        return new Exporters\MediaExporter($this->streamer);
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
        $result = $this->forwardCallTo($streamer = $this->getStreamer(), $method, $arguments);

        return ($result === $streamer) ? $this : $result;
    }
}

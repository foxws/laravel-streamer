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

/**
 * @method \Foxws\Streamer\Support\Streamer fresh()
 * @method $this setStreamer(\Foxws\Streamer\Support\ShakaStreamer $streamer)
 * @method \Foxws\Streamer\Filesystem\MediaCollection getMediaCollection()
 * @method ?\Foxws\Streamer\Support\CommandBuilder getBuilder()
 * @method \Foxws\Streamer\Support\CommandBuilder builder()
 * @method \Illuminate\Support\Collection<int, array<string, mixed>> streams()
 * @method $this addVideoStream(string $input, string $output, array<string, mixed> $options = [])
 * @method $this addAudioStream(string $input, string $output, array<string, mixed> $options = [])
 * @method $this addTextStream(string $input, string $output, array<string, mixed> $options = [])
 * @method $this addStream(\Foxws\Streamer\Support\Stream|array<string, mixed> $stream)
 * @method $this withMpdOutput(string $path)
 * @method $this withHlsMasterPlaylist(string $path)
 * @method \Foxws\Streamer\Support\EncryptionKey withAESEncryption(string $keyFilename = 'key', \Foxws\Streamer\Support\ProtectionScheme|string|null $protectionScheme = null, ?string $label = null)
 * @method $this withKeyRotationDuration(int $seconds)
 * @method $this useSystemBinaries(bool $use = true)
 * @method array<string, mixed> getCommand()
 * @method \Foxws\Streamer\Support\StreamerResult streamWithBuilder(\Foxws\Streamer\Support\CommandBuilder $builder)
 * @method $this withSegmentDuration(float $seconds)
 * @method $this withStreamingMode(string $mode)
 * @method $this withEncryption(array<string, mixed> $encryptionConfig)
 * @method $this withManifestFormat(array<int, string> $formats)
 * @method $this withResolutions(array<int, string> $resolutions = [])
 * @method $this withSegmentPerFile(bool $enabled = true)
 * @method $this withAudioCodecs(array<int, string> $codecs)
 * @method $this withVideoCodecs(array<int, string> $codecs)
 * @method $this withGenerateIframePlaylist(bool $enabled = true)
 * @method $this withLowLatencyDashMode(bool $enabled = true)
 * @method $this withLimitResolutionBy(string $dimension)
 * @method $this withHwaccelApi(string $api)
 * @method $this withChannelLayouts(string|array<int, string> $layouts)
 * @method $this withSegmentFolder(string $folder)
 * @method $this withExtraInputArgs(string $args)
 * @method $this withOption(string $key, mixed $value)
 * @method \Illuminate\Support\Collection<int, array<string, mixed>> getStreams()
 * @method \Illuminate\Support\Collection<string, mixed> getOptions()
 * @method ?string getMpdOutput()
 * @method ?string getHlsOutput()
 * @method array<string, mixed> buildArray()
 * @method array<string, mixed> build()
 */
class MediaOpener
{
    use ForwardsCalls;

    protected Disk $disk;

    protected Streamer $streamer;

    protected MediaCollection $collection;

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

    public function getDisk(): Disk
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
     *
     * @param  string|UploadedFile|array<int, string|UploadedFile>  $paths
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
     *
     * @param  string|UploadedFile|array<int, string|UploadedFile>  $paths
     */
    public function openFromDisk(Filesystem|string $disk, $paths): self
    {
        return $this->fromDisk($disk)->open($paths);
    }

    public function get(): MediaCollection
    {
        return $this->collection;
    }

    /**
     * @param  iterable<array-key, mixed>  $items
     */
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

    /**
     * @param  array<int, mixed>  $arguments
     * @param  string  $method
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        $result = $this->forwardCallTo($streamer = $this->getStreamer(), $method, $arguments);

        return ($result === $streamer) ? $this : $result;
    }
}

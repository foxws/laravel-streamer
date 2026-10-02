<?php

declare(strict_types=1);

namespace Foxws\Streamer\Http;

use Foxws\Streamer\Exceptions\MediaNotFoundException;
use Foxws\Streamer\Filesystem\Disk;
use Foxws\Streamer\Filesystem\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;

class DynamicHLSPlaylist implements Responsable
{
    protected Disk $disk;

    protected ?Media $media = null;

    /**
     * Callable to retrieve the URL for encryption keys.
     *
     * @var (callable(string): string)|null
     */
    protected $keyUrlResolver = null;

    /**
     * Callable to retrieve the URL for media files.
     *
     * @var (callable(string): string)|null
     */
    protected $mediaUrlResolver = null;

    /**
     * Callable to retrieve the URL for playlist files.
     *
     * @var (callable(string): string)|null
     */
    protected $playlistUrlResolver = null;

    /**
     * Cache for resolved key URLs.
     *
     * @var array<string, string>
     */
    protected array $keyCache = [];

    /**
     * Cache for resolved media URLs.
     *
     * @var array<string, string>
     */
    protected array $mediaCache = [];

    /**
     * Cache for resolved playlist URLs.
     *
     * @var array<string, string>
     */
    protected array $playlistCache = [];

    /**
     * Uses the 'filesystems.default' disk as default.
     */
    public function __construct(?string $disk = null)
    {
        $this->fromDisk($disk ?: Config::string('filesystems.default'));
    }

    /**
     * Set the disk to open files from.
     *
     * @param  Disk|Filesystem|string  $disk
     */
    public function fromDisk($disk): self
    {
        $this->disk = Disk::make($disk);

        return $this;
    }

    /**
     * Instantiates a Media object for the given path and clears the cache.
     */
    public function open(string $path): self
    {
        $this->media = Media::make($this->disk, $path, false);

        $this->keyCache = [];
        $this->playlistCache = [];
        $this->mediaCache = [];

        return $this;
    }

    /**
     * Set the key URL resolver.
     */
    public function setKeyUrlResolver(callable $resolver): self
    {
        $this->keyUrlResolver = $resolver;
        $this->keyCache = [];

        return $this;
    }

    /**
     * Set the media URL resolver.
     */
    public function setMediaUrlResolver(callable $resolver): self
    {
        $this->mediaUrlResolver = $resolver;
        $this->mediaCache = [];

        return $this;
    }

    /**
     * Set the playlist URL resolver.
     */
    public function setPlaylistUrlResolver(callable $resolver): self
    {
        $this->playlistUrlResolver = $resolver;
        $this->playlistCache = [];

        return $this;
    }

    /**
     * Get the key URL resolver.
     */
    public function getKeyUrlResolver(): ?callable
    {
        return $this->keyUrlResolver;
    }

    /**
     * Get the media URL resolver.
     */
    public function getMediaUrlResolver(): ?callable
    {
        return $this->mediaUrlResolver;
    }

    /**
     * Get the playlist URL resolver.
     */
    public function getPlaylistUrlResolver(): ?callable
    {
        return $this->playlistUrlResolver;
    }

    /**
     * Resolve a key URL using the configured resolver.
     */
    protected function resolveKeyUrl(string $key): string
    {
        if (! $this->keyUrlResolver) {
            return $key;
        }

        return $this->keyCache[$key] ??= ($this->keyUrlResolver ?? fn (string $key): string => $key)($key);
    }

    /**
     * Resolve a media URL using the configured resolver.
     */
    protected function resolveMediaUrl(string $filename): string
    {
        if (! $this->mediaUrlResolver) {
            return $filename;
        }

        return $this->mediaCache[$filename] ??= ($this->mediaUrlResolver ?? fn (string $filename): string => $filename)($filename);
    }

    /**
     * Resolve a playlist URL using the configured resolver.
     */
    protected function resolvePlaylistUrl(string $filename): string
    {
        if (! $this->playlistUrlResolver) {
            return $filename;
        }

        return $this->playlistCache[$filename] ??= ($this->playlistUrlResolver ?? fn (string $filename): string => $filename)($filename);
    }

    /**
     * Parses the lines into a Collection.
     *
     * @return Collection<int, string>
     */
    public static function parseLines(string $lines): Collection
    {
        return Collection::make(preg_split('/\n|\r\n?/', $lines) ?: []);
    }

    /**
     * Returns a boolean whether the line contains a .M3U8 playlist filename,
     * a .TS segment filename, or a media filename (.mp4, .m4s, .m4a, .m4v, .aac, .vtt).
     * Returns false if the line is already a full URL.
     */
    protected static function lineHasMediaFilename(string $line): bool
    {
        // Skip lines that are already full URLs or comments
        if ($line === '' || $line[0] === '#' || str_starts_with($line, 'http://') || str_starts_with($line, 'https://')) {
            return false;
        }

        // Check file extensions - common ones first for performance
        return str_ends_with($line, '.m3u8')
            || str_ends_with($line, '.ts')
            || str_ends_with($line, '.m4s')
            || str_ends_with($line, '.mp4')
            || str_ends_with($line, '.m4a')
            || str_ends_with($line, '.m4v')
            || str_ends_with($line, '.aac')
            || str_ends_with($line, '.vtt');
    }

    /**
     * Returns the filename of the encryption key.
     */
    protected static function extractKeyFromExtLine(string $line): ?string
    {
        preg_match('/#EXT-X-KEY:METHOD=[^,]+,URI="([^"]+)"/', $line, $matches);

        return $matches[1] ?? null;
    }

    /**
     * Extract playlist URI from EXT-X-MEDIA line.
     */
    protected static function extractPlaylistFromExtMediaLine(string $line): ?string
    {
        if (! str_starts_with($line, '#EXT-X-MEDIA:')) {
            return null;
        }

        preg_match('/URI="([^"]+)"/', $line, $matches);

        return $matches[1] ?? null;
    }

    /**
     * Extract media URI from EXT-X-MAP line.
     */
    protected static function extractMediaFromExtMapLine(string $line): ?string
    {
        if (! str_starts_with($line, '#EXT-X-MAP:')) {
            return null;
        }

        preg_match('/URI="([^"]+)"/', $line, $matches);

        return $matches[1] ?? null;
    }

    /**
     * Returns the processed content of the playlist.
     */
    public function get(): string
    {
        if (! $this->media) {
            throw new \RuntimeException('No playlist file opened. Call open() first.');
        }

        return $this->getProcessedPlaylist($this->media->getPath());
    }

    /**
     * Returns a collection of all processed segment playlists
     * and the processed main playlist.
     *
     * @return Collection<string, string>
     */
    public function all(): Collection
    {
        $path = $this->openedMedia()->getPath();

        return static::parseLines($this->read($path))
            ->filter(static fn ($line) => static::lineHasMediaFilename($line))
            ->mapWithKeys(fn ($segmentPlaylist) => [$segmentPlaylist => $this->getProcessedPlaylist($segmentPlaylist)])
            ->prepend($this->getProcessedPlaylist($path), $path);
    }

    /**
     * Processes the given playlist.
     */
    public function getProcessedPlaylist(string $playlistPath): string
    {
        return static::parseLines($this->read($playlistPath))->map(function (string $line) {
            if (static::lineHasMediaFilename($line)) {
                // Use playlist resolver for .m3u8 files, media resolver for everything else
                return str_ends_with($line, '.m3u8')
                    ? $this->resolvePlaylistUrl($line)
                    : $this->resolveMediaUrl($line);
            }

            // Handle #EXT-X-KEY encryption lines
            $key = static::extractKeyFromExtLine($line);
            if ($key) {
                return preg_replace(
                    '/URI="'.preg_quote($key, '/').'"/',
                    'URI="'.$this->resolveKeyUrl($key).'"',
                    $line
                );
            }

            // Handle #EXT-X-MEDIA lines with URI attribute
            $playlistUri = static::extractPlaylistFromExtMediaLine($line);
            if ($playlistUri) {
                return str_replace(
                    'URI="'.$playlistUri.'"',
                    'URI="'.$this->resolvePlaylistUrl($playlistUri).'"',
                    $line
                );
            }

            // Handle #EXT-X-MAP lines with URI attribute (initialization segments)
            $mapUri = static::extractMediaFromExtMapLine($line);
            if ($mapUri) {
                return str_replace(
                    'URI="'.$mapUri.'"',
                    'URI="'.$this->resolveMediaUrl($mapUri).'"',
                    $line
                );
            }

            return $line;
        })->implode(PHP_EOL);
    }

    /**
     * Returns the playlist as an HTTP response.
     */
    public function toResponse($request)
    {
        return Response::make($this->get(), 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
        ]);
    }

    /**
     * The opened playlist, failing clearly when open() wasn't called.
     */
    protected function openedMedia(): Media
    {
        return $this->media ?? throw new \RuntimeException('No playlist file opened. Call open() first.');
    }

    /**
     * The contents of a file on the playlist's disk, failing clearly when it's gone.
     */
    protected function read(string $path): string
    {
        return $this->disk->get($path) ?? throw new MediaNotFoundException("The playlist file {$path} doesn't exist on its disk.");
    }
}

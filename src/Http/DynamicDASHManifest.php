<?php

declare(strict_types=1);

namespace Foxws\Streamer\Http;

use Foxws\Streamer\Filesystem\Disk;
use Foxws\Streamer\Filesystem\Media;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;

class DynamicDASHManifest implements Responsable
{
    protected ?Disk $disk = null;

    protected ?Media $media = null;

    /**
     * Callable to retrieve the URL for media files.
     */
    protected $mediaUrlResolver = null;

    /**
     * Callable to retrieve the URL for initialization segments.
     */
    protected $initUrlResolver = null;

    /**
     * Cache for resolved media URLs.
     */
    protected array $mediaCache = [];

    /**
     * Cache for resolved init URLs.
     */
    protected array $initCache = [];

    /**
     * Uses the 'filesystems.default' disk as default.
     */
    public function __construct(?string $disk = null)
    {
        $this->fromDisk($disk ?: Config::string('filesystems.default'));
    }

    /**
     * Set the disk to open files from.
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

        $this->mediaCache = [];
        $this->initCache = [];

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
     * Set the initialization segment URL resolver.
     */
    public function setInitUrlResolver(callable $resolver): self
    {
        $this->initUrlResolver = $resolver;
        $this->initCache = [];

        return $this;
    }

    /**
     * Get the media URL resolver.
     */
    public function getMediaUrlResolver(): ?callable
    {
        return $this->mediaUrlResolver;
    }

    /**
     * Get the initialization segment URL resolver.
     */
    public function getInitUrlResolver(): ?callable
    {
        return $this->initUrlResolver;
    }

    /**
     * Resolve a media URL using the configured resolver.
     */
    protected function resolveMediaUrl(string $filename): string
    {
        return $this->mediaCache[$filename] ??= call_user_func($this->mediaUrlResolver, $filename);
    }

    /**
     * Resolve an initialization segment URL using the configured resolver.
     */
    protected function resolveInitUrl(string $filename): string
    {
        return $this->initCache[$filename] ??= call_user_func($this->initUrlResolver, $filename);
    }

    /**
     * Returns the processed content of the manifest.
     */
    public function get(): string
    {
        if (! $this->media) {
            throw new \RuntimeException('No manifest file opened. Call open() first.');
        }

        $content = $this->disk->get($this->media->getPath());

        return $this->processManifest($content);
    }

    /**
     * Processes the DASH manifest MPD file.
     */
    protected function processManifest(string $content): string
    {
        // Replace BaseURL elements with resolved URLs
        if ($this->mediaUrlResolver) {
            $content = preg_replace_callback(
                '/<BaseURL>([^<]+)<\/BaseURL>/',
                fn ($matches) => '<BaseURL>'.$this->resolveMediaUrl($matches[1]).'</BaseURL>',
                $content
            );
        }

        // Replace initialization attribute URLs
        if ($this->initUrlResolver) {
            $content = preg_replace_callback(
                '/initialization="([^"]+)"/',
                fn ($matches) => 'initialization="'.$this->resolveInitUrl($matches[1]).'"',
                $content
            );
        }

        // Replace media attribute URLs
        if ($this->mediaUrlResolver) {
            $content = preg_replace_callback(
                '/media="([^"]+)"/',
                fn ($matches) => 'media="'.$this->resolveMediaUrl($matches[1]).'"',
                $content
            );
        }

        return $content;
    }

    /**
     * Returns the manifest as an HTTP response.
     */
    public function toResponse($request)
    {
        return Response::make($this->get(), 200, [
            'Content-Type' => 'application/dash+xml',
        ]);
    }
}

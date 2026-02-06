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
        $manifest = $this->processManifest($content);

        // Ensure XML declaration is present for proper parsing by media players
        if (! str_starts_with(trim($manifest), '<?xml')) {
            $manifest = '<?xml version="1.0" encoding="UTF-8"?>'."\n".$manifest;
        }

        return $manifest;
    }

    /**
     * Processes the DASH manifest MPD file.
     */
    protected function processManifest(string $content): string
    {
        // Convert SegmentTemplate with $Number$ to SegmentList
        $content = $this->expandSegmentTemplates($content);

        // Replace BaseURL elements with resolved URLs
        if ($this->mediaUrlResolver) {
            $content = preg_replace_callback(
                '/<BaseURL>([^<]+)<\/BaseURL>/',
                fn ($matches) => '<BaseURL>'.htmlspecialchars($this->resolveMediaUrl($matches[1]), ENT_XML1 | ENT_COMPAT, 'UTF-8').'</BaseURL>',
                $content
            );
        }

        // Replace initialization attribute URLs (sourceURL for SegmentList)
        if ($this->initUrlResolver) {
            $content = preg_replace_callback(
                '/(initialization|sourceURL)="([^"]+)"/',
                fn ($matches) => $matches[1].'="'.htmlspecialchars($this->resolveInitUrl($matches[2]), ENT_XML1 | ENT_COMPAT, 'UTF-8').'"',
                $content
            );
        }

        // Replace media attribute URLs
        if ($this->mediaUrlResolver) {
            $content = preg_replace_callback(
                '/media="([^"]+)"/',
                fn ($matches) => 'media="'.htmlspecialchars($this->resolveMediaUrl($matches[1]), ENT_XML1 | ENT_COMPAT, 'UTF-8').'"',
                $content
            );
        }

        return $content;
    }

    /**
     * Expand SegmentTemplate with $Number$ placeholders to SegmentList.
     */
    protected function expandSegmentTemplates(string $content): string
    {
        return preg_replace_callback(
            '/<SegmentTemplate\s+([^>]*)>(.*?)<\/SegmentTemplate>/s',
            function ($matches) {
                $attributes = $matches[1];
                $innerContent = $matches[2];

                // Extract template attributes
                preg_match('/timescale="(\d+)"/', $attributes, $timescaleMatch);
                preg_match('/initialization="([^"]+)"/', $attributes, $initMatch);
                preg_match('/media="([^"]+)"/', $attributes, $mediaMatch);
                preg_match('/startNumber="(\d+)"/', $attributes, $startMatch);

                $timescale = $timescaleMatch[1] ?? '1';
                $initUrl = $initMatch[1] ?? '';
                $mediaTemplate = $mediaMatch[1] ?? '';
                $startNumber = (int) ($startMatch[1] ?? 1);

                // Check if media template contains $Number$
                if (! str_contains($mediaTemplate, '$Number$')) {
                    return $matches[0]; // Return unchanged
                }

                // Parse SegmentTimeline to get segment durations
                preg_match('/<SegmentTimeline>(.*?)<\/SegmentTimeline>/s', $innerContent, $timelineMatch);
                if (! $timelineMatch) {
                    return $matches[0]; // No timeline, return unchanged
                }

                // Parse segment timing from <S> elements
                preg_match_all('/<S\s+([^>]*?)\/>?/', $timelineMatch[1], $sMatches);

                $segmentDurations = [];
                foreach ($sMatches[1] as $sAttrs) {
                    // Extract d (duration) and r (repeat count)
                    preg_match('/d="(\d+)"/', $sAttrs, $dMatch);
                    preg_match('/r="(-?\d+)"/', $sAttrs, $rMatch);

                    $duration = $dMatch[1] ?? null;
                    $repeat = isset($rMatch[1]) ? (int) $rMatch[1] : 0;

                    if ($duration !== null) {
                        // Add duration once, then repeat if needed
                        $repeatCount = $repeat >= 0 ? $repeat : 0;
                        for ($i = 0; $i <= $repeatCount; $i++) {
                            $segmentDurations[] = $duration;
                        }
                    }
                }

                $segmentCount = count($segmentDurations);

                // Build SegmentList
                $segmentList = '<SegmentList timescale="'.$timescale.'">';

                // Add Initialization element
                if ($initUrl) {
                    $segmentList .= '<Initialization sourceURL="'.$initUrl.'"/>';
                }

                // Add SegmentTimeline (required for timing information)
                $segmentList .= $timelineMatch[0];

                // Add SegmentURL elements with duration attributes
                for ($i = 0; $i < $segmentCount; $i++) {
                    $segmentNumber = $startNumber + $i;
                    $segmentUrl = str_replace('$Number$', (string) $segmentNumber, $mediaTemplate);
                    $duration = $segmentDurations[$i];
                    $segmentList .= '<SegmentURL media="'.$segmentUrl.'" duration="'.$duration.'"/>';
                }

                $segmentList .= '</SegmentList>';

                return $segmentList;
            },
            $content
        );
    }

    /**
     * Returns the manifest as an HTTP response.
     */
    public function toResponse($request)
    {
        $response = Response::make($this->get(), 200);

        $response->header('Content-Type', 'application/dash+xml; charset=UTF-8');
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');

        return $response;
    }
}

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
     *
     * Handles both concrete URLs and SegmentTemplate patterns with DASH
     * template variables ($Number$, $Time$, etc). When template variables
     * are present, the SegmentTemplate is expanded into a SegmentList
     * with individually signed URLs.
     */
    protected function processManifest(string $content): string
    {
        $xml = new \SimpleXMLElement($content);
        $xml->registerXPathNamespace('mpd', 'urn:mpeg:dash:schema:mpd:2011');

        // Replace BaseURL elements with resolved URLs
        if ($this->mediaUrlResolver) {
            foreach ($xml->xpath('//mpd:BaseURL') as $baseUrl) {
                $baseUrl[0] = $this->resolveMediaUrl((string) $baseUrl);
            }
        }

        // Replace sourceURL attributes on existing elements (before template expansion
        // to avoid double-resolving URLs added by expandSegmentTemplate)
        if ($this->initUrlResolver) {
            foreach ($xml->xpath('//*[@sourceURL]') as $element) {
                $attrs = $element->attributes();
                $attrs['sourceURL'] = $this->resolveInitUrl((string) $attrs['sourceURL']);
            }
        }

        // Replace media attributes on existing SegmentURL elements (before template expansion
        // to avoid double-resolving URLs added by expandSegmentTemplate)
        if ($this->mediaUrlResolver) {
            foreach ($xml->xpath('//mpd:SegmentURL[@media]') as $segmentUrl) {
                $attrs = $segmentUrl->attributes();
                $attrs['media'] = $this->resolveMediaUrl((string) $attrs['media']);
            }
        }

        // Process SegmentTemplate elements
        foreach ($xml->xpath('//mpd:SegmentTemplate') as $template) {
            $attrs = $template->attributes();

            $mediaPattern = isset($attrs['media']) ? (string) $attrs['media'] : null;
            $initFile = isset($attrs['initialization']) ? (string) $attrs['initialization'] : null;
            $hasTemplateVars = $mediaPattern && preg_match('/\$[A-Za-z]+\$/', $mediaPattern);

            if ($hasTemplateVars && $this->mediaUrlResolver) {
                $this->expandSegmentTemplate($template);
            } else {
                // Concrete URLs — sign directly
                if ($this->mediaUrlResolver && $mediaPattern) {
                    $attrs['media'] = $this->resolveMediaUrl($mediaPattern);
                }

                if ($this->initUrlResolver && $initFile) {
                    $attrs['initialization'] = $this->resolveInitUrl($initFile);
                }
            }
        }

        return $xml->asXML();
    }

    /**
     * Expands a SegmentTemplate with template variables into a SegmentList
     * with individually signed segment URLs.
     *
     * Parses the SegmentTimeline to determine segment count, then generates
     * concrete signed URLs for each segment number.
     */
    protected function expandSegmentTemplate(\SimpleXMLElement $template): void
    {
        $attrs = $template->attributes();
        $mediaPattern = (string) $attrs['media'];
        $initFile = isset($attrs['initialization']) ? (string) $attrs['initialization'] : null;
        $startNumber = isset($attrs['startNumber']) ? (int) (string) $attrs['startNumber'] : 1;
        $timescale = isset($attrs['timescale']) ? (int) (string) $attrs['timescale'] : 1;

        // Calculate segment count from SegmentTimeline
        $segmentCount = $this->calculateSegmentCount($template);

        // Get the parent Representation element
        $representation = $template->xpath('..')[0];

        // Build the SegmentList element
        $segmentList = $representation->addChild('SegmentList');
        $segmentList->addAttribute('timescale', (string) $timescale);

        // Add signed initialization
        if ($initFile) {
            $init = $segmentList->addChild('Initialization');

            if ($this->initUrlResolver) {
                $init->addAttribute('sourceURL', $this->resolveInitUrl($initFile));
            } else {
                $init->addAttribute('sourceURL', $initFile);
            }
        }

        // Add signed segment URLs
        for ($i = $startNumber; $i < $startNumber + $segmentCount; $i++) {
            // Replace $Number$ first, then any remaining template variables
            $filename = str_replace('$Number$', (string) $i, $mediaPattern);
            $filename = preg_replace('/\$[A-Za-z]+\$/', (string) $i, $filename);

            $segmentUrl = $segmentList->addChild('SegmentURL');
            $segmentUrl->addAttribute('media', $this->resolveMediaUrl($filename));
        }

        // Remove the original SegmentTemplate
        $this->removeXmlChild($template);
    }

    /**
     * Calculates the total number of segments from a SegmentTimeline.
     *
     * Parses <S> elements with t (start), d (duration), and r (repeat) attributes
     * to determine the total segment count.
     */
    protected function calculateSegmentCount(\SimpleXMLElement $template): int
    {
        $template->registerXPathNamespace('mpd', 'urn:mpeg:dash:schema:mpd:2011');
        $segments = $template->xpath('mpd:SegmentTimeline/mpd:S');

        if (empty($segments)) {
            return 1;
        }

        $count = 0;

        foreach ($segments as $s) {
            $attrs = $s->attributes();
            $repeat = isset($attrs['r']) ? (int) (string) $attrs['r'] : 0;
            $count += 1 + $repeat;
        }

        return $count;
    }

    /**
     * Removes a child element from its parent in SimpleXML.
     */
    protected function removeXmlChild(\SimpleXMLElement $child): void
    {
        $dom = dom_import_simplexml($child);
        $dom->parentNode->removeChild($dom);
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

<?php

declare(strict_types=1);

namespace Foxws\Streamer\Exceptions;

use RuntimeException;

class StreamerException extends RuntimeException
{
    public static function noManifests(): self
    {
        return new self('Add an HLS playlist or a DASH manifest with withHlsPlaylist() or withDashManifest().');
    }

    public static function missingManifest(string $manifest): self
    {
        return new self("Shaka Streamer finished without writing [{$manifest}].");
    }
}

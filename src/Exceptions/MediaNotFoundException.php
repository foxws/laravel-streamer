<?php

declare(strict_types=1);

namespace Foxws\Streamer\Exceptions;

use Exception;

class MediaNotFoundException extends Exception
{
    public static function unreadable(string $path): self
    {
        return new self("Can't read {$path}: it no longer exists on its disk.");
    }

    public static function playlistFile(string $path): self
    {
        return new self("The playlist file {$path} doesn't exist on its disk.");
    }

    public static function manifestFile(string $path): self
    {
        return new self("The manifest file {$path} doesn't exist on its disk.");
    }
}

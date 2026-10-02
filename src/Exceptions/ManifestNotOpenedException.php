<?php

declare(strict_types=1);

namespace Foxws\Streamer\Exceptions;

use RuntimeException;

/**
 * Extends PHP's RuntimeException, which DynamicDASHManifest::get() threw for
 * this before, so existing catch blocks keep working.
 */
class ManifestNotOpenedException extends RuntimeException
{
    public static function playlist(): self
    {
        return new self('No playlist file opened. Call open() first.');
    }

    public static function manifest(): self
    {
        return new self('No manifest file opened. Call open() first.');
    }
}

<?php

declare(strict_types=1);

namespace Foxws\Streamer\Exceptions;

class ManifestProcessingException extends RuntimeException
{
    public static function regexFailed(string $error): self
    {
        return new self("Processing the DASH manifest failed: {$error}");
    }
}

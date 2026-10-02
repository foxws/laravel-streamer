<?php

declare(strict_types=1);

namespace Foxws\Streamer\Exceptions;

class EncryptionKeyFileException extends RuntimeException
{
    public static function missing(): self
    {
        return new self('The encryption key was generated without a key file.');
    }

    public static function unreadable(string $path): self
    {
        return new self("Can't read the key file {$path}.");
    }
}

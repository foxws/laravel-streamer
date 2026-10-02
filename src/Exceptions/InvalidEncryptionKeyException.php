<?php

declare(strict_types=1);

namespace Foxws\Streamer\Exceptions;

use InvalidArgumentException;

class InvalidEncryptionKeyException extends InvalidArgumentException
{
    public static function notHex(): self
    {
        return new self('The encryption key must be a non-empty hex string.');
    }
}

<?php

declare(strict_types=1);

namespace Foxws\Streamer\Exceptions;

use LogicException;

class MissingStreamerException extends LogicException
{
    public static function noResolver(): self
    {
        return new self('MediaOpenerFactory needs a streamer or a streamer resolver.');
    }
}

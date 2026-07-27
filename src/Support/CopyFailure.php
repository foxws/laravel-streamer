<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

/**
 * A file that failed to copy to the destination disk.
 */
final readonly class CopyFailure
{
    public function __construct(
        public string $source,
        public string $target,
        public string $error,
    ) {}

    public function __toString(): string
    {
        return "{$this->target}: {$this->error}";
    }
}

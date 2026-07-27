<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

/**
 * A single file to be copied from a local source path to a relative target
 * path on the destination disk.
 */
final readonly class FileOperation
{
    public function __construct(
        public string $absolutePath,
        public string $targetPath,
    ) {}
}

<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A single encryption key file discovered on disk after streaming,
 * as produced by key rotation (e.g. "encryption_0.key", "encryption_1.key").
 */
final readonly class EncryptionKeyFile implements Arrayable
{
    public function __construct(
        public string $path,
        public string $filename,
        public string $content,
    ) {}

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'filename' => $this->filename,
            'content' => $this->content,
        ];
    }
}

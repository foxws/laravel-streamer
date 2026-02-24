<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class VideoResolution implements Arrayable
{
    /** @var array<int, string> */
    private const RESOLUTIONS = [
        144  => '144p',
        240  => '240p',
        360  => '360p',
        480  => '480p',
        576  => '576p',
        720  => '720p',
        1080 => '1080p',
        1440 => '1440p',
        2160 => '4k',
        4320 => '8k',
    ];

    public static function make(int $height): static
    {
        return new static($height);
    }

    public function __construct(
        protected int $height,
    ) {}

    /** @return Collection<int, string> */
    public function all(): Collection
    {
        return Collection::make(self::RESOLUTIONS)
            ->filter(fn (string $name, int $maxHeight) => $this->height >= $maxHeight)
            ->values();
    }

    public function first(): ?string
    {
        return $this->all()->first();
    }

    public function toArray(): array
    {
        return $this->all()->toArray();
    }
}

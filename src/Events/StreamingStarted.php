<?php

declare(strict_types=1);

namespace Foxws\Streamer\Events;

use Foxws\Streamer\Filesystem\MediaCollection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamingStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public MediaCollection $mediaCollection,
        public array $options
    ) {}
}

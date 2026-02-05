<?php

declare(strict_types=1);

namespace Foxws\Streamer\Events;

use Foxws\Streamer\Support\StreamerResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamingCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public StreamerResult $result,
        public float $executionTime
    ) {}
}

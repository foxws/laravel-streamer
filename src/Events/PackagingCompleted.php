<?php

declare(strict_types=1);

namespace Foxws\Streamer\Events;

use Foxws\Streamer\Support\PackagerResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PackagingCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PackagerResult $result,
        public float $executionTime
    ) {}
}

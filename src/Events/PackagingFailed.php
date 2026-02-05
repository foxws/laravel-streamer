<?php

declare(strict_types=1);

namespace Foxws\Streamer\Events;

use Exception;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PackagingFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Exception $exception,
        public array $context = []
    ) {}
}

<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

class ProcessOutput
{
    public function __construct(
        private array $all = [],
        private array $errors = [],
        private array $out = [],
    ) {}

    public function all(): array
    {
        return $this->all;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function out(): array
    {
        return $this->out;
    }
}

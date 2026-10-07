<?php

declare(strict_types=1);

namespace Foxws\Streamer;

use Foxws\Media\Executables\Binary;
use Illuminate\Support\Facades\Config;

enum StreamerExecutable: string implements Binary
{
    case ShakaStreamer = 'shaka-streamer';

    public function identifier(): string
    {
        return $this->value;
    }

    public function configuredPath(): string
    {
        $configured = Config::get('streamer.binary');

        return is_string($configured) && $configured !== '' ? $configured : $this->value;
    }

    public function environmentKey(): string
    {
        return 'STREAMER_BINARY';
    }

    public function versionArguments(): array
    {
        return ['--version'];
    }
}

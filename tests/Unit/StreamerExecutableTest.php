<?php

declare(strict_types=1);

use Foxws\Streamer\StreamerExecutable;

it('runs shaka-streamer from the PATH by default', function (): void {
    expect(StreamerExecutable::ShakaStreamer->identifier())->toBe('shaka-streamer')
        ->and(StreamerExecutable::ShakaStreamer->configuredPath())->toBe('shaka-streamer')
        ->and(StreamerExecutable::ShakaStreamer->environmentKey())->toBe('STREAMER_BINARY')
        ->and(StreamerExecutable::ShakaStreamer->versionArguments())->toBe(['--version']);
});

it('uses the configured binary', function (): void {
    config(['streamer.binary' => '/opt/venv/bin/shaka-streamer']);

    expect(StreamerExecutable::ShakaStreamer->configuredPath())->toBe('/opt/venv/bin/shaka-streamer');
});

it('falls back to the PATH when the configured binary is empty', function (): void {
    config(['streamer.binary' => '']);

    expect(StreamerExecutable::ShakaStreamer->configuredPath())->toBe('shaka-streamer');
});

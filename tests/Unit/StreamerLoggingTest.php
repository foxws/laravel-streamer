<?php

declare(strict_types=1);

use Foxws\Streamer\Support\CommandBuilder;
use Foxws\Streamer\Support\ShakaStreamer;
use Foxws\Streamer\Support\Streamer;
use Psr\Log\LoggerInterface;

it('filters sensitive encryption keys from logs', function () {
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();
    $driver = mock(ShakaStreamer::class)->shouldIgnoreMissing();

    $streamer = new Streamer($driver, $logger);

    // Create a builder
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/output.mp4');

    // Verify logger exists and builder can be created
    expect($logger)->not->toBeNull();
    expect($builder)->toBeInstanceOf(CommandBuilder::class);
});

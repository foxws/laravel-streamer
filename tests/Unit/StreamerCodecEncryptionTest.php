<?php

declare(strict_types=1);

use Foxws\Streamer\Support\CommandBuilder;
use Foxws\Streamer\Support\ShakaStreamer;
use Foxws\Streamer\Support\Streamer;
use Psr\Log\LoggerInterface;

it('can create streamer instance', function () {
    $driver = mock(ShakaStreamer::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $streamer = new Streamer($driver, $logger);

    expect($streamer)->toBeInstanceOf(Streamer::class);
});

it('can build configuration with command builder', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/output.mp4');

    expect($builder)->toBeInstanceOf(CommandBuilder::class);
});

it('can build complete configuration', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/h264.mp4')
        ->withMpdOutput('/tmp/manifest.mpd');

    $config = $builder->build();

    expect($config)->toHaveKey('input_config')
        ->and($config)->toHaveKey('pipeline_config');
});

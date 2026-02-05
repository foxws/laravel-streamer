<?php

declare(strict_types=1);

use Foxws\Streamer\Support\Streamer;
use Foxws\Streamer\Support\CommandBuilder;
use Foxws\Streamer\Support\ShakaStreamer;
use Foxws\Streamer\Support\PackagerResult;
use Psr\Log\LoggerInterface;

it('can create command builder for encryption setup', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/output.mp4');

    expect($builder)->toBeInstanceOf(CommandBuilder::class);
});

it('can build configuration with multiple options', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.mp4')
        ->addAudioStream('/tmp/input.mp4', '/tmp/audio.mp4')
        ->withMpdOutput('/tmp/manifest.mpd');

    $config = $builder->build();

    expect($config)->toHaveKey('input_config')
        ->and($config)->toHaveKey('pipeline_config')
        ->and($config['input_config'])->toHaveKey('inputs');
});

it('streamer handles configuration correctly', function () {
    $driver = mock(ShakaStreamer::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $streamer = new Streamer($driver, $logger);

    expect($streamer)->toBeInstanceOf(Streamer::class);
});

it('command builder validates input configuration', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/output.mp4');

    $config = $builder->build();

    expect($config['input_config']['inputs'])->not->toBeEmpty();
});

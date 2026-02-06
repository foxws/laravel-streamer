<?php

declare(strict_types=1);

use Foxws\Streamer\Support\CommandBuilder;

it('can set audio codecs on the builder', function () {
    $builder = CommandBuilder::make()
        ->addAudioStream('/tmp/input.mp4', '/tmp/audio.m4s')
        ->withAudioCodecs(['aac', 'opus']);

    $config = $builder->build();

    expect($config['pipeline_config'])->toHaveKey('audio_codecs')
        ->and($config['pipeline_config']['audio_codecs'])->toBe(['aac', 'opus']);
});

it('can set video codecs on the builder', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withVideoCodecs(['h264', 'hw:vp9']);

    $config = $builder->build();

    expect($config['pipeline_config'])->toHaveKey('video_codecs')
        ->and($config['pipeline_config']['video_codecs'])->toBe(['h264', 'hw:vp9']);
});

it('can set both audio and video codecs on the builder', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->addAudioStream('/tmp/input.mp4', '/tmp/audio.m4s')
        ->withAudioCodecs(['aac', 'opus'])
        ->withVideoCodecs(['h264', 'hw:vp9']);

    $config = $builder->build();

    expect($config['pipeline_config'])
        ->toHaveKey('audio_codecs')
        ->toHaveKey('video_codecs')
        ->and($config['pipeline_config']['audio_codecs'])->toBe(['aac', 'opus'])
        ->and($config['pipeline_config']['video_codecs'])->toBe(['h264', 'hw:vp9']);
});

it('can enable segment per file on the builder', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withSegmentPerFile();

    $config = $builder->build();

    expect($config['pipeline_config'])->toHaveKey('segment_per_file')
        ->and($config['pipeline_config']['segment_per_file'])->toBeTrue();
});

it('can disable segment per file on the builder', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withSegmentPerFile(false);

    $config = $builder->build();

    expect($config['pipeline_config'])->toHaveKey('segment_per_file')
        ->and($config['pipeline_config']['segment_per_file'])->toBeFalse();
});

it('defaults streaming mode to vod', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s');

    $config = $builder->build();

    expect($config['pipeline_config']['streaming_mode'])->toBe('vod');
});

it('can set streaming mode to live', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withStreamingMode('live');

    $config = $builder->build();

    expect($config['pipeline_config']['streaming_mode'])->toBe('live');
});

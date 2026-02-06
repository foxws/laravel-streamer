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

it('can set resolutions on the builder', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withResolutions(['1080p', '720p', '480p']);

    $config = $builder->build();

    expect($config['pipeline_config'])->toHaveKey('resolutions')
        ->and($config['pipeline_config']['resolutions'])->toBe(['1080p', '720p', '480p']);
});

it('can set a single resolution on the builder', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withResolutions(['4k']);

    $config = $builder->build();

    expect($config['pipeline_config']['resolutions'])->toBe(['4k']);
});

it('can set manifest format to dash only', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withManifestFormat(['dash']);

    $config = $builder->build();

    expect($config['pipeline_config']['manifest_format'])->toBe(['dash']);
});

it('can set manifest format to both dash and hls', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withManifestFormat(['dash', 'hls']);

    $config = $builder->build();

    expect($config['pipeline_config']['manifest_format'])->toBe(['dash', 'hls']);
});

it('overrides auto-detected manifest format when explicitly set', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withMpdOutput('/tmp/manifest.mpd')
        ->withHlsMasterPlaylist('/tmp/master.m3u8')
        ->withManifestFormat(['dash']);

    $config = $builder->build();

    expect($config['pipeline_config']['manifest_format'])->toBe(['dash']);
});

it('can enable low latency dash mode', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withLowLatencyDashMode();

    $config = $builder->build();

    expect($config['pipeline_config'])->toHaveKey('low_latency_dash_mode')
        ->and($config['pipeline_config']['low_latency_dash_mode'])->toBeTrue();
});

it('can disable low latency dash mode', function () {
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/video.m4s')
        ->withLowLatencyDashMode(false);

    $config = $builder->build();

    expect($config['pipeline_config'])->toHaveKey('low_latency_dash_mode')
        ->and($config['pipeline_config']['low_latency_dash_mode'])->toBeFalse();
});

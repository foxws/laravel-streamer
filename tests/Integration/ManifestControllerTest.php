<?php

declare(strict_types=1);

use Foxws\Streamer\Facades\Streamer;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::disk('local')->makeDirectory('streamer-output');
});

afterEach(function () {
    Storage::disk('local')->deleteDirectory('streamer-output');
});

it('can generate streamer configuration', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withMpdOutput('manifest.mpd');

    expect($streamer)->not->toBeNull();
});

it('can get command builder from configured streamer', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withMpdOutput('manifest.mpd');

    $builder = $streamer->builder();

    expect($builder)->not->toBeNull();
});

it('can configure mpd output', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withMpdOutput('manifest.mpd');

    expect($streamer)->not->toBeNull();
});

it('can configure hls master playlist', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withHlsMasterPlaylist('playlist.m3u8');

    expect($streamer)->not->toBeNull();
});

it('can configure with options', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withMpdOutput('manifest.mpd')
        ->withOption('custom_key', 'custom_value');

    expect($streamer)->not->toBeNull();
});

it('can chain configuration methods', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withMpdOutput('manifest.mpd')
        ->withHlsMasterPlaylist('playlist.m3u8')
        ->withSegmentDuration(10);

    expect($streamer)->not->toBeNull();
});

it('can get streamer packager driver', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v');

    $driver = $streamer->getPackager();

    expect($driver)->not->toBeNull();
});

it('can get media collection from streamer', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v');

    $collection = $streamer->getMediaCollection();

    expect($collection)->not->toBeNull();
});

it('can configure encryption with options', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'output.m4v')
        ->withMpdOutput('manifest.mpd')
        ->withOption('encryption', ['key' => 'secret']);

    expect($streamer)->not->toBeNull();
});

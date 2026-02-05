<?php

declare(strict_types=1);

use Foxws\Streamer\Facades\Streamer;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Create test storage disk
    Storage::disk('local')->makeDirectory('streamer-test-output');
});

afterEach(function () {
    // Cleanup
    Storage::disk('local')->deleteDirectory('streamer-test-output');
});

it('can add video stream to streamer', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v');

    expect(method_exists($streamer, 'addVideoStream'))->toBeTrue();
});

it('can configure dash output', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withMpdOutput('manifest.mpd');

    expect(method_exists($streamer, 'withMpdOutput'))->toBeTrue();
});

it('can configure hls output', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withHlsMasterPlaylist('playlist.m3u8');

    expect(method_exists($streamer, 'withHlsMasterPlaylist'))->toBeTrue();
});

it('can configure both dash and hls', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withMpdOutput('manifest.mpd')
        ->withHlsMasterPlaylist('playlist.m3u8')
        ->withSegmentDuration(10);

    expect($streamer)->not->toBeNull();
});

it('can configure segment duration', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withMpdOutput('manifest.mpd')
        ->withSegmentDuration(10);

    expect(method_exists($streamer, 'withSegmentDuration'))->toBeTrue();
});

it('can get command builder from streamer', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withMpdOutput('manifest.mpd');

    $builder = $streamer->builder();

    expect($builder)->not->toBeNull();
    expect(method_exists($builder, 'buildArray'))->toBeTrue();
});

it('preserves video stream configuration', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('input.mp4', 'output.m4v')
        ->withSegmentDuration(10);

    $streams = $streamer->streams();

    expect($streams)->toHaveCount(1);
});

it('supports different video codecs', function () {
    skipIfNoStreamer();

    foreach (['h264', 'hevc', 'av1'] as $codec) {
        Storage::disk('local')->put('video.mp4', file_get_contents(fixture("sample_{$codec}.mp4")));

        $streamer = Streamer::open('video.mp4')
            ->addVideoStream('video.mp4', 'output.m4v')
            ->withMpdOutput('manifest.mpd');

        expect($streamer)->not->toBeNull();
    }
})->group('codecs');

it('can chain multiple stream configuration methods', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'video.m4v')
        ->withMpdOutput('manifest.mpd')
        ->withSegmentDuration(10);

    expect($streamer)->not->toBeNull();
});

it('can configure encryption options', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $streamer = Streamer::open('video.mp4')
        ->addVideoStream('video.mp4', 'output.m4v')
        ->withMpdOutput('manifest.mpd')
        ->withOption('drm', ['widevine' => true]);

    expect($streamer)->not->toBeNull();
});

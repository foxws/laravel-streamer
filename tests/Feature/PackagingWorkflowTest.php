<?php

declare(strict_types=1);

use Foxws\Streamer\MediaOpener;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('export');
});

it('can create media opener instance', function () {
    $opener = new MediaOpener;

    expect($opener)->toBeInstanceOf(MediaOpener::class);
});

it('can switch between disks during packaging', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));
    Storage::disk('export')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $opener1 = Streamer::fromDisk('local')->open('video.mp4');
    $opener2 = Streamer::fromDisk('export')->open('video.mp4');

    expect($opener1->getDisk()->getName())->toBe('local');
    expect($opener2->getDisk()->getName())->toBe('export');
});

it('can handle workflow from open to export', function () {
    Storage::disk('local')->put('input.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('input.mp4')->export();

    expect($exporter)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
});

it('can create dynamic HLS playlist', function () {
    $playlist = MediaOpener::dynamicHLSPlaylist();

    expect($playlist)->toBeInstanceOf(\Foxws\Streamer\Http\DynamicHLSPlaylist::class);
});

it('can create dynamic DASH manifest', function () {
    $manifest = MediaOpener::dynamicDASHManifest();

    expect($manifest)->toBeInstanceOf(\Foxws\Streamer\Http\DynamicDASHManifest::class);
});

it('can create dynamic playlists with specific disk', function () {
    $playlist = MediaOpener::dynamicHLSPlaylist('export');
    $manifest = MediaOpener::dynamicDASHManifest('local');

    expect($playlist)->toBeInstanceOf(\Foxws\Streamer\Http\DynamicHLSPlaylist::class);
    expect($manifest)->toBeInstanceOf(\Foxws\Streamer\Http\DynamicDASHManifest::class);
});

it('can cleanup temporary files', function () {
    $opener = new MediaOpener;

    $result = $opener->cleanupTemporaryFiles();

    expect($result)->toBe($opener);
});

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

it('can create dash manifest', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));
    $videoPath = Storage::disk('local')->path('video.mp4');
    $outputDir = Storage::disk('local')->path('streamer-test-output');

    $result = Streamer::open('video.mp4')
        ->withManifestFormats(['dash'])
        ->withSegmentSize(10)
        ->export()
        ->toDisk('local', 'streamer-test-output');

    expect($result)->toBeTrue();
    expect(Storage::disk('local')->exists('streamer-test-output/manifest.mpd'))->toBeTrue();
});

it('can create hls manifest', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = Streamer::open('video.mp4')
        ->withManifestFormats(['hls'])
        ->withSegmentSize(10)
        ->export()
        ->toDisk('local', 'streamer-test-output');

    expect($result)->toBeTrue();
    expect(Storage::disk('local')->exists('streamer-test-output/playlist.m3u8'))->toBeTrue();
});

it('can create both dash and hls manifests', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = Streamer::open('video.mp4')
        ->withManifestFormats(['dash', 'hls'])
        ->withSegmentSize(10)
        ->export()
        ->toDisk('local', 'streamer-test-output');

    expect($result)->toBeTrue();
    expect(Storage::disk('local')->exists('streamer-test-output/manifest.mpd'))->toBeTrue();
    expect(Storage::disk('local')->exists('streamer-test-output/playlist.m3u8'))->toBeTrue();
});

it('creates valid dash manifest structure', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = Streamer::open('video.mp4')
        ->withManifestFormats(['dash'])
        ->export()
        ->toDisk('local', 'streamer-test-output');

    expect($result)->toBeTrue();

    $manifestPath = Storage::disk('local')->path('streamer-test-output/manifest.mpd');
    expect(file_exists($manifestPath))->toBeTrue();

    $manifestContent = file_get_contents($manifestPath);
    expect($manifestContent)->toContain('<?xml');
    expect($manifestContent)->toContain('<MPD');
    expect($manifestContent)->toContain('Period');
});

it('creates valid hls manifest structure', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = Streamer::open('video.mp4')
        ->withManifestFormats(['hls'])
        ->export()
        ->toDisk('local', 'streamer-test-output');

    expect($result)->toBeTrue();

    $playlistPath = Storage::disk('local')->path('streamer-test-output/playlist.m3u8');
    expect(file_exists($playlistPath))->toBeTrue();

    $playlistContent = file_get_contents($playlistPath);
    expect($playlistContent)->toContain('#EXTM3U');
    expect($playlistContent)->toContain('#EXT-X-VERSION');
});

it('creates segment files for dash', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = Streamer::open('video.mp4')
        ->withManifestFormats(['dash'])
        ->withSegmentSize(10)
        ->export()
        ->toDisk('local', 'streamer-test-output');

    expect($result)->toBeTrue();

    $files = Storage::disk('local')->files('streamer-test-output');
    $segmentFiles = array_filter($files, fn ($f) => str_contains($f, '.m4s'));

    expect(count($segmentFiles))->toBeGreaterThan(0);
});

it('creates init segment for dash', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = Streamer::open('video.mp4')
        ->withManifestFormats(['dash'])
        ->export()
        ->toDisk('local', 'streamer-test-output');

    expect($result)->toBeTrue();

    $files = Storage::disk('local')->files('streamer-test-output');
    $initSegments = array_filter($files, fn ($f) => str_contains($f, 'init'));

    expect(count($initSegments))->toBeGreaterThan(0);
});

it('supports different video codecs', function () {
    skipIfNoStreamer();

    foreach (['h264', 'hevc', 'av1'] as $codec) {
        Storage::disk('local')->put('video.mp4', file_get_contents(fixture("sample_{$codec}.mp4")));

        $result = Streamer::open('video.mp4')
            ->withManifestFormats(['dash'])
            ->export()
            ->toDisk('local', 'streamer-test-output');

        expect($result)->toBeTrue();
        expect(Storage::disk('local')->exists('streamer-test-output/manifest.mpd'))->toBeTrue();

        // Cleanup for next iteration
        Storage::disk('local')->deleteDirectory('streamer-test-output');
        Storage::disk('local')->makeDirectory('streamer-test-output');
    }
})->group('codecs');

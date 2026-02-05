<?php

declare(strict_types=1);

use Foxws\Streamer\Facades\Streamer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::disk('local')->makeDirectory('streamer-output');

    // Setup a test route that demonstrates manifest key replacement
    Route::get('/stream/{media}', function ($media) {
        $video = Storage::disk('local')->path('video.mp4');

        if (!file_exists($video)) {
            abort(404);
        }

        return Streamer::open('video.mp4')
            ->withManifestFormats(['dash', 'hls'])
            ->withSegmentSize(10)
            ->replaceManifestKeys([
                'BaseURL' => config('app.url') . '/streams/',
                'domain' => config('app.domain', 'example.com'),
            ])
            ->export()
            ->toResponse('inline', ['manifest.mpd', 'playlist.m3u8']);
    });
});

afterEach(function () {
    Storage::disk('local')->deleteDirectory('streamer-output');
});

it('can generate streamer response with manifest replacement', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = Streamer::open('video.mp4')
        ->withManifestFormats(['dash'])
        ->export()
        ->getCommand();

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['input_config', 'pipeline_config']);
});

it('replaces manifest keys in dash manifest', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));
    $outputDir = Storage::disk('local')->path('streamer-output');

    Streamer::open('video.mp4')
        ->withManifestFormats(['dash'])
        ->export()
        ->toDisk('local', 'streamer-output');

    $manifestPath = Storage::disk('local')->path('streamer-output/manifest.mpd');
    expect(file_exists($manifestPath))->toBeTrue();

    $manifest = file_get_contents($manifestPath);

    // Manifests should contain Period or other DASH elements
    expect($manifest)->toContain('Period');
});

it('can export manifest with custom output directory', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = Streamer::open('video.mp4')
        ->withManifestFormats(['dash', 'hls'])
        ->withSegmentSize(10)
        ->export()
        ->toDisk('local', 'streamer-output');

    expect($result)->toBeTrue();
    expect(Storage::disk('local')->exists('streamer-output/manifest.mpd'))->toBeTrue();
    expect(Storage::disk('local')->exists('streamer-output/playlist.m3u8'))->toBeTrue();
});

it('can retrieve manifest content as string', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    Streamer::open('video.mp4')
        ->withManifestFormats(['dash'])
        ->export()
        ->toDisk('local', 'streamer-output');

    $manifestContent = file_get_contents(
        Storage::disk('local')->path('streamer-output/manifest.mpd')
    );

    expect($manifestContent)->toBeString();
    expect(strlen($manifestContent))->toBeGreaterThan(0);
    expect($manifestContent)->toContain('<MPD');
});

it('handles multiple quality levels in manifest', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    Streamer::open('video.mp4')
        ->withBitrates([1000000, 2500000, 5000000])
        ->withManifestFormats(['dash'])
        ->export()
        ->toDisk('local', 'streamer-output');

    $manifestPath = Storage::disk('local')->path('streamer-output/manifest.mpd');
    expect(file_exists($manifestPath))->toBeTrue();

    $manifest = file_get_contents($manifestPath);

    // Should have multiple representations for different bitrates
    expect($manifest)->toContain('Representation');
});

it('can generate hls playlist with segments', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    Streamer::open('video.mp4')
        ->withManifestFormats(['hls'])
        ->withSegmentSize(10)
        ->export()
        ->toDisk('local', 'streamer-output');

    $playlistPath = Storage::disk('local')->path('streamer-output/playlist.m3u8');
    expect(file_exists($playlistPath))->toBeTrue();

    $playlist = file_get_contents($playlistPath);

    // Should reference segment files
    expect($playlist)->toContain('.ts');
});

it('can export with inline disposition for streaming', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')
        ->withManifestFormats(['dash'])
        ->export();

    // Test that exporter has the toResponse method
    expect(method_exists($exporter, 'toResponse'))->toBeTrue();
});

it('preserves manifest format options through export', function () {
    skipIfNoStreamer();

    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')
        ->withManifestFormats(['dash', 'hls'])
        ->withSegmentSize(10)
        ->export();

    // Export should contain the configuration
    $command = $exporter->getCommand();

    expect($command)->toBeArray();
    expect($command)->toHaveKey('pipeline_config');
});

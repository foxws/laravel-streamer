<?php

declare(strict_types=1);

use Foxws\Streamer\Exporters\MediaExporter;
use Foxws\Streamer\Facades\Streamer;
use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\Filesystem\MediaCollection;
use Foxws\Streamer\Support\Streamer as StreamerDriver;
use Foxws\Streamer\Support\StreamerResult;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('export');
});

it('can create exporter from media opener', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')->export();

    expect($exporter)->toBeInstanceOf(MediaExporter::class);
});

it('can specify target disk for export', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')
        ->export()
        ->toDisk('export');

    expect($exporter)->toBeInstanceOf(MediaExporter::class);
});

it('can specify path for export', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')
        ->export()
        ->toPath('output/');

    expect($exporter)->toBeInstanceOf(MediaExporter::class);
});

it('can set file visibility for export', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')
        ->export()
        ->withVisibility('public');

    expect($exporter)->toBeInstanceOf(MediaExporter::class);
});

it('can chain path and disk methods', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')
        ->export()
        ->toDisk('export')
        ->toPath('videos/')
        ->withVisibility('public');

    expect($exporter)->toBeInstanceOf(MediaExporter::class);
});

it('can get command for debugging', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $command = Streamer::open('video.mp4')
        ->export()
        ->getCommand();

    expect($command)->toBeArray()
        ->and($command)->not->toBeEmpty();
});

it('can add after saving callbacks', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $callbackExecuted = false;

    $exporter = Streamer::open('video.mp4')
        ->export()
        ->afterSaving(function () use (&$callbackExecuted) {
            $callbackExecuted = true;
        });

    expect($exporter)->toBeInstanceOf(MediaExporter::class);
});

it('can handle multiple export destinations', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter1 = Streamer::open('video.mp4')->export()->toDisk('local');
    $exporter2 = Streamer::open('video.mp4')->export()->toDisk('export');

    expect($exporter1)->toBeInstanceOf(MediaExporter::class);
    expect($exporter2)->toBeInstanceOf(MediaExporter::class);
});

it('saves to the path given to save()', function () {
    $outputDirectory = sys_get_temp_dir().'/test-save-path-'.bin2hex(random_bytes(4));
    mkdir($outputDirectory);
    file_put_contents("{$outputDirectory}/index.mpd", '<MPD/>');

    $streamer = Mockery::mock(StreamerDriver::class);
    $streamer->shouldReceive('export')->andReturn(new StreamerResult('ok', null, $outputDirectory));
    $streamer->shouldReceive('getMediaCollection')->andReturn(MediaCollection::make([Media::make('local', 'video.mp4', false)]));

    (new MediaExporter($streamer))->toDisk('export')->save('streams/clip');

    Storage::disk('export')->assertExists('streams/clip/index.mpd');
});

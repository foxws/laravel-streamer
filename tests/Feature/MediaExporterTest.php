<?php

declare(strict_types=1);

use Foxws\Streamer\Facades\Streamer;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('export');
});

it('can create exporter from media opener', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')->export();

    expect($exporter)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
});

it('can specify target disk for export', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')
        ->export()
        ->toDisk('export');

    expect($exporter)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
});

it('can specify path for export', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')
        ->export()
        ->toPath('output/');

    expect($exporter)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
});

it('can set file visibility for export', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')
        ->export()
        ->withVisibility('public');

    expect($exporter)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
});

it('can chain path and disk methods', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')
        ->export()
        ->toDisk('export')
        ->toPath('videos/')
        ->withVisibility('public');

    expect($exporter)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
});

it('can get command for debugging', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $command = Streamer::open('video.mp4')
        ->export()
        ->getCommand();

    expect($command)->toBeString();
});

it('can add after saving callbacks', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $callbackExecuted = false;

    $exporter = Streamer::open('video.mp4')
        ->export()
        ->afterSaving(function () use (&$callbackExecuted) {
            $callbackExecuted = true;
        });

    expect($exporter)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
});

it('can handle multiple export destinations', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter1 = Streamer::open('video.mp4')->export()->toDisk('local');
    $exporter2 = Streamer::open('video.mp4')->export()->toDisk('export');

    expect($exporter1)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
    expect($exporter2)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
});

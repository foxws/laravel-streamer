<?php

declare(strict_types=1);

use Foxws\Streamer\Facades\Shaka;
use Foxws\Streamer\MediaOpener;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('export');
});

it('service provider is registered', function () {
    expect(app()->getProviders(\Foxws\Streamer\ShakaServiceProvider::class))->not->toBeEmpty();
});

it('registers media opener in service container', function () {
    $opener = app(MediaOpener::class);

    expect($opener)->toBeInstanceOf(MediaOpener::class);
});

it('can resolve media opener via container', function () {
    $opener = app()->make(MediaOpener::class);

    expect($opener)->toBeInstanceOf(MediaOpener::class);
});

it('facade resolves to media opener instance', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $opener = Streamer::open('video.mp4');

    expect($opener)->toBeInstanceOf(MediaOpener::class);
});

it('can access facade methods statically', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $opener = Streamer::fromDisk('local')->open('video.mp4');

    expect($opener)->toBeInstanceOf(MediaOpener::class);
});

it('can instantiate multiple independent packagers', function () {
    Storage::disk('local')->put('video1.mp4', file_get_contents(fixture('sample_h264.mp4')));
    Storage::disk('local')->put('video2.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $packager1 = Streamer::open('video1.mp4');
    $packager2 = Streamer::open('video2.mp4');

    expect($packager1->get()->first()->getPath())->toBe('video1.mp4');
    expect($packager2->get()->first()->getPath())->toBe('video2.mp4');
});

it('facade methods return independent state', function () {
    Storage::disk('local')->put('video1.mp4', file_get_contents(fixture('sample_h264.mp4')));
    Storage::disk('local')->put('video2.mp4', file_get_contents(fixture('sample_h264.mp4')));

    // Resolve fresh instances for independent state
    $opener1 = app(MediaOpener::class)->open('video1.mp4');
    $opener2 = app(MediaOpener::class)->open('video2.mp4');

    expect($opener1->get()->first()->getPath())->toBe('video1.mp4');
    expect($opener2->get()->first()->getPath())->toBe('video2.mp4');
});

it('can chain facade methods with instance methods', function () {
    Storage::disk('local')->put('test.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = Streamer::fromDisk('local')
        ->open('test.mp4');

    expect($result->getDisk()->getName())->toBe('local');
    expect($result->get()->count())->toBe(1);
});

it('can export from facade chain', function () {
    Storage::disk('local')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $exporter = Streamer::open('video.mp4')->export();

    expect($exporter)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
});

<?php

declare(strict_types=1);

use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\Filesystem\MediaCollection;
use Foxws\Streamer\MediaOpener;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('export');
});

it('can open media file using fixture', function () {
    $opener = new MediaOpener;

    // Copy fixture to fake storage
    Storage::disk('local')->put('test-video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = $opener->open('test-video.mp4');

    expect($result)->toBeInstanceOf(MediaOpener::class);
    expect($result->get())->toBeInstanceOf(MediaCollection::class);
    expect($result->get()->count())->toBe(1);
    expect($result->get()->first())->toBeInstanceOf(Media::class);
});

it('can open multiple media files', function () {
    Storage::disk('local')->put('video1.mp4', file_get_contents(fixture('sample_h264.mp4')));
    Storage::disk('local')->put('video2.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $opener = new MediaOpener;
    $result = $opener->open(['video1.mp4', 'video2.mp4']);

    expect($result->get()->count())->toBe(2);
});

it('can use facade to open media', function () {
    Storage::disk('local')->put('test-video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $opener = Streamer::open('test-video.mp4');

    expect($opener)->toBeInstanceOf(MediaOpener::class);
    expect($opener->get()->count())->toBe(1);
});

it('can open media from specific disk', function () {
    Storage::disk('export')->put('video.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $opener = Streamer::fromDisk('export')->open('video.mp4');

    expect($opener->getDisk()->getName())->toBe('export');
    expect($opener->get()->count())->toBe(1);
});

it('can chain disk and open operations', function () {
    Storage::disk('local')->put('test.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $result = Streamer::fromDisk('local')
        ->open('test.mp4');

    expect($result->getDisk()->getName())->toBe('local');
    expect($result->get()->first())->toBeInstanceOf(Media::class);
});

it('can get packager instance from opener', function () {
    $opener = new MediaOpener;

    expect($opener->getPackager())->toBeInstanceOf(\Foxws\Streamer\Support\Packager::class);
});

it('can export from media opener', function () {
    Storage::disk('local')->put('test.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $opener = Streamer::open('test.mp4');
    $exporter = $opener->export();

    expect($exporter)->toBeInstanceOf(\Foxws\Streamer\Exporters\MediaExporter::class);
});

it('validates that media collection is not empty before opening', function () {
    $streamer = app(\Foxws\Streamer\Support\Streamer::class);
    $emptyCollection = MediaCollection::make([]);

    $streamer->open($emptyCollection);
})->throws(InvalidArgumentException::class, 'MediaCollection cannot be empty');

it('can clone media opener instance', function () {
    Storage::disk('local')->put('test.mp4', file_get_contents(fixture('sample_h264.mp4')));

    $opener1 = Streamer::fromDisk('local')->open('test.mp4');
    $opener2 = $opener1->clone();

    expect($opener2)->not->toBe($opener1);
    expect($opener2->getDisk()->getName())->toBe($opener1->getDisk()->getName());
});

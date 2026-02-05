<?php

declare(strict_types=1);

use Foxws\Streamer\Filesystem\Disk;
use Foxws\Streamer\MediaOpener;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'filesystems.disks.test-disk' => [
            'driver' => 'local',
            'root' => storage_path('app/test'),
        ],
        'filesystems.disks.another-disk' => [
            'driver' => 'local',
            'root' => storage_path('app/another'),
        ],
    ]);
});

it('can use fromDisk to set the disk', function () {
    $opener = new MediaOpener;

    $result = $opener->fromDisk('test-disk');

    expect($result)->toBe($opener);
    expect($opener->getDisk())->toBeInstanceOf(Disk::class);
    expect($opener->getDisk()->getName())->toBe('test-disk');
});

it('can switch between disks', function () {
    $opener = new MediaOpener;

    expect($opener->getDisk()->getName())->toBe(config('filesystems.default'));

    $opener->fromDisk('test-disk');
    expect($opener->getDisk()->getName())->toBe('test-disk');

    $opener->fromDisk('another-disk');
    expect($opener->getDisk()->getName())->toBe('another-disk');
});

it('clone method preserves disk', function () {
    $opener = new MediaOpener;
    $opener->fromDisk('test-disk');

    $cloned = $opener->clone();

    expect($cloned->getDisk()->getName())->toBe('test-disk');
    expect($cloned)->not->toBe($opener);
});

it('can accept Disk instance in fromDisk', function () {
    $disk = Disk::make('test-disk');
    $opener = new MediaOpener;

    $result = $opener->fromDisk($disk);

    expect($result)->toBe($opener);
    expect($opener->getDisk())->toBe($disk);
});

it('can accept Filesystem instance in fromDisk', function () {
    $filesystem = Storage::disk('test-disk');
    $opener = new MediaOpener;

    $result = $opener->fromDisk($filesystem);

    expect($result)->toBe($opener);
    expect($opener->getDisk())->toBeInstanceOf(Disk::class);
});

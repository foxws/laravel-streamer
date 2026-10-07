<?php

declare(strict_types=1);

use Foxws\Media\Executables\Executables;
use Foxws\Media\Facades\Media;
use Foxws\Streamer\StreamerBuilder;
use Foxws\Streamer\StreamerExecutable;
use Foxws\Streamer\StreamerServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

it('adds streamer() to opened media', function (): void {
    Storage::fake('media');
    Media::fake();

    $builder = Media::fromDisk('media')->open('clip.mp4')->streamer();

    expect($builder)->toBeInstanceOf(StreamerBuilder::class)
        ->and($builder->media()->paths())->toBe(['clip.mp4'])
        ->and($builder->disk()->name())->toBe('media');
});

it('registers shaka-streamer with media:info and about', function (): void {
    expect(app(Executables::class)->all())->toContain(StreamerExecutable::ShakaStreamer);
});

it('merges and publishes the config', function (): void {
    expect(config('streamer.binary'))->toBe('shaka-streamer')
        ->and(config('streamer.timeout'))->toBe(14400)
        ->and(config('streamer.system_binaries'))->toBeFalse()
        ->and(array_values(ServiceProvider::pathsToPublish(StreamerServiceProvider::class, 'streamer-config')))
        ->toBe([config_path('streamer.php')]);
});

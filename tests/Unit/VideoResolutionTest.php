<?php

declare(strict_types=1);

use Foxws\Streamer\Support\VideoResolution;

it('lists every standard tier up to the source height', function () {
    expect(VideoResolution::make(1080)->all()->all())->toBe([
        '144p', '240p', '360p', '480p', '576p', '720p', '1080p',
    ]);
});

it('first() returns the lowest qualifying tier', function () {
    expect(VideoResolution::make(1080)->first())->toBe('144p');
});

it('last() returns the native/highest qualifying tier', function () {
    expect(VideoResolution::make(1080)->last())->toBe('1080p')
        ->and(VideoResolution::make(720)->last())->toBe('720p')
        ->and(VideoResolution::make(4000)->last())->toBe('4k');
});

it('returns null when the source is below the lowest standard tier', function () {
    expect(VideoResolution::make(100)->last())->toBeNull()
        ->and(VideoResolution::make(100)->first())->toBeNull();
});

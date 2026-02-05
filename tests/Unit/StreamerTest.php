<?php

declare(strict_types=1);

use Foxws\Streamer\Support\ShakaStreamer;
use Foxws\Streamer\Support\Streamer;

it('can create streamer instance', function () {
    $driver = mock(ShakaStreamer::class);

    $streamer = new Streamer($driver);

    expect($streamer)->toBeInstanceOf(Streamer::class);
    expect($streamer->getStreamer())->toBe($driver);
});

it('can set and get driver', function () {
    $driver1 = mock(ShakaStreamer::class);
    $driver2 = mock(ShakaStreamer::class);

    $streamer = new Streamer($driver1);

    expect($streamer->getStreamer())->toBe($driver1);
});

it('can create fresh instance', function () {
    $driver = mock(ShakaStreamer::class);

    $streamer1 = new Streamer($driver);
    $streamer2 = $streamer1->fresh();

    expect($streamer2)->toBeInstanceOf(Streamer::class);
    expect($streamer2)->not->toBe($streamer1);
    expect($streamer2->getStreamer())->toBe($driver);
});

it('fresh instance has same driver', function () {
    $driver = mock(ShakaStreamer::class);

    $streamer1 = new Streamer($driver);
    $streamer2 = $streamer1->fresh();

    expect($streamer2->getStreamer())->toBe($streamer1->getStreamer());
});

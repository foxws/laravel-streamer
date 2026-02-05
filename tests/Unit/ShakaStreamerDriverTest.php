<?php

declare(strict_types=1);

use Foxws\Streamer\Support\ShakaStreamer;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('laravel-streamer.streamer.python_binary', 'python3');
    Config::set('laravel-streamer.streamer.timeout', 3600);
});

it('can create streamer with valid configuration', function () {
    $streamer = ShakaStreamer::create();

    expect($streamer)->toBeInstanceOf(ShakaStreamer::class);
});

it('can get and set timeout', function () {
    $streamer = ShakaStreamer::create();

    expect($streamer->getTimeout())->toBe(3600);

    $streamer->setTimeout(7200);

    expect($streamer->getTimeout())->toBe(7200);
});

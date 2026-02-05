<?php

declare(strict_types=1);

use Foxws\Streamer\Exceptions\ExecutableNotFoundException;
use Foxws\Streamer\Support\ShakaPackager;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('laravel-streamer.packager.binaries', '/usr/local/bin/packager');
    Config::set('laravel-streamer.timeout', 3600);
});

it('throws exception when binary not found', function () {
    Config::set('laravel-streamer.packager.binaries', '/nonexistent/packager');

    ShakaPackager::create();
})->throws(ExecutableNotFoundException::class);

it('can create driver with valid configuration', function () {
    $driver = ShakaPackager::create();

    expect($driver)->toBeInstanceOf(ShakaPackager::class);
    expect($driver->getName())->toBe('packager');
});

it('can get and set timeout', function () {
    $driver = ShakaPackager::create();

    expect($driver->getTimeout())->toBe(3600);

    $driver->setTimeout(7200);

    expect($driver->getTimeout())->toBe(7200);
});

it('can get binary path from config', function () {
    $driver = ShakaPackager::create();

    expect($driver->getBinaryPath())->toBe('/usr/local/bin/packager');
});

it('respects custom binary path configuration', function () {
    $customPath = '/custom/path/to/packager';

    Config::set('laravel-streamer.packager.binaries', $customPath);

    try {
        ShakaPackager::create();
    } catch (ExecutableNotFoundException $e) {
        expect($e->getMessage())->toContain($customPath);
    }
});

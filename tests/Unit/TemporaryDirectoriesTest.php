<?php

declare(strict_types=1);

use Foxws\Streamer\Filesystem\TemporaryDirectories;

it('creates temporary directory in root path', function () {
    $tempDirs = new TemporaryDirectories('/tmp/test-temp');

    $directory = $tempDirs->create();

    expect($directory)->toStartWith('/tmp/test-temp/')
        ->and(is_dir($directory))->toBeTrue();

    // Cleanup
    $tempDirs->deleteAll();
    expect(is_dir($directory))->toBeFalse();
});

it('creates cache directory when cache root is configured', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    $cacheDir = $tempDirs->createCache();

    expect($cacheDir)->toStartWith(sys_get_temp_dir().'/test-cache/')
        ->and(is_dir($cacheDir))->toBeTrue();

    // Cleanup
    $tempDirs->deleteAll();
    expect(is_dir($cacheDir))->toBeFalse();
});

it('falls back to root when cache root is not configured', function () {
    $tempDirs = new TemporaryDirectories('/tmp/test-temp');

    $cacheDir = $tempDirs->createCache();

    expect($cacheDir)->toStartWith('/tmp/test-temp/')
        ->and(is_dir($cacheDir))->toBeTrue();

    // Cleanup
    $tempDirs->deleteAll();
});

it('reports cache storage availability correctly', function () {
    $withCache = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );
    $withoutCache = new TemporaryDirectories(sys_get_temp_dir().'/test-temp');

    expect($withCache->hasCacheStorage())->toBeTrue()
        ->and($withoutCache->hasCacheStorage())->toBeFalse();
});

it('deletes all directories including both temp and cache', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    $tempDir = $tempDirs->create();
    $cacheDir = $tempDirs->createCache();

    expect(is_dir($tempDir))->toBeTrue()
        ->and(is_dir($cacheDir))->toBeTrue();

    $tempDirs->deleteAll();

    expect(is_dir($tempDir))->toBeFalse()
        ->and(is_dir($cacheDir))->toBeFalse();
});

it('creates unique directories on multiple calls', function () {
    $tempDirs = new TemporaryDirectories('/tmp/test-temp');

    $dir1 = $tempDirs->create();
    $dir2 = $tempDirs->create();
    $dir3 = $tempDirs->createCache();

    expect($dir1)->not->toBe($dir2)
        ->and($dir2)->not->toBe($dir3)
        ->and($dir1)->not->toBe($dir3);

    // Cleanup
    $tempDirs->deleteAll();
});

it('handles trailing slashes in root paths', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp/',
        sys_get_temp_dir().'/test-cache/'
    );

    $tempDir = $tempDirs->create();
    $cacheDir = $tempDirs->createCache();

    // Should not have double slashes
    expect($tempDir)->not->toContain('//')
        ->and($cacheDir)->not->toContain('//');

    // Cleanup
    $tempDirs->deleteAll();
});

<?php

declare(strict_types=1);

use Foxws\Streamer\Exceptions\InsufficientStorageException;
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

it('throws when free space is below the configured minimum', function () {
    $tempDirs = new TemporaryDirectories(sys_get_temp_dir().'/test-temp', null, PHP_INT_MAX);

    expect(fn () => $tempDirs->create())->toThrow(InsufficientStorageException::class);
});

it('applies its own minimum free space check to cache directories', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache',
        0,
        PHP_INT_MAX
    );

    expect(fn () => $tempDirs->createCache())->toThrow(InsufficientStorageException::class);
});

it('keeps the temporary and cache minimum free space checks independent', function () {
    // Only the main root's floor is set: create() throws, createCache() does not.
    $mainOnly = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache',
        PHP_INT_MAX
    );

    expect(fn () => $mainOnly->create())->toThrow(InsufficientStorageException::class);
    expect(is_dir($mainOnly->createCache()))->toBeTrue();
    $mainOnly->deleteAll();

    // Only the cache root's floor is set: createCache() throws, create() does not.
    $cacheOnly = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache',
        0,
        PHP_INT_MAX
    );

    expect(is_dir($cacheOnly->create()))->toBeTrue();
    expect(fn () => $cacheOnly->createCache())->toThrow(InsufficientStorageException::class);
    $cacheOnly->deleteAll();
});

it('does not check free space when the minimum is zero', function () {
    $tempDirs = new TemporaryDirectories(sys_get_temp_dir().'/test-temp', null, 0);

    $directory = $tempDirs->create();

    expect(is_dir($directory))->toBeTrue();

    $tempDirs->deleteAll();
});

it('does not check free space when the minimum is negative', function () {
    $tempDirs = new TemporaryDirectories(sys_get_temp_dir().'/test-temp', null, -1);

    $directory = $tempDirs->create();

    expect(is_dir($directory))->toBeTrue();

    $tempDirs->deleteAll();
});

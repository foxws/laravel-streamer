<?php

declare(strict_types=1);

use Foxws\Streamer\Http\DynamicDASHManifest;
use Foxws\Streamer\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'filesystems.disks.test-disk' => [
            'driver' => 'local',
            'root' => storage_path('app/test'),
        ],
    ]);
});

// HLS Playlist Tests
it('can set key url resolver on hls playlist', function () {
    $playlist = new DynamicHLSPlaylist;

    $resolver = fn ($key) => route('video.key', ['key' => $key]);

    $result = $playlist->setKeyUrlResolver($resolver);

    expect($result)->toBe($playlist);
    expect($playlist->getKeyUrlResolver())->toBe($resolver);
});

it('can set media url resolver on hls playlist', function () {
    $playlist = new DynamicHLSPlaylist;

    $resolver = fn ($filename) => Storage::disk('public')->url($filename);

    $result = $playlist->setMediaUrlResolver($resolver);

    expect($result)->toBe($playlist);
    expect($playlist->getMediaUrlResolver())->toBe($resolver);
});

it('can set playlist url resolver on hls playlist', function () {
    $playlist = new DynamicHLSPlaylist;

    $resolver = fn ($filename) => route('video.playlist', ['playlist' => $filename]);

    $result = $playlist->setPlaylistUrlResolver($resolver);

    expect($result)->toBe($playlist);
    expect($playlist->getPlaylistUrlResolver())->toBe($resolver);
});

it('can chain multiple resolver setters on hls playlist', function () {
    $playlist = new DynamicHLSPlaylist;

    $result = $playlist
        ->setKeyUrlResolver(fn ($key) => "keys/{$key}")
        ->setMediaUrlResolver(fn ($file) => "media/{$file}")
        ->setPlaylistUrlResolver(fn ($file) => "playlists/{$file}");

    expect($result)->toBe($playlist);
    expect($playlist->getKeyUrlResolver())->not->toBeNull();
    expect($playlist->getMediaUrlResolver())->not->toBeNull();
    expect($playlist->getPlaylistUrlResolver())->not->toBeNull();
});

it('clears cache when setting new resolver on hls playlist', function () {
    $playlist = new DynamicHLSPlaylist;

    $playlist->setKeyUrlResolver(fn ($key) => "url1-{$key}");
    $playlist->setKeyUrlResolver(fn ($key) => "url2-{$key}");

    expect($playlist->getKeyUrlResolver())->not->toBeNull();
});

// DASH Manifest Tests
it('can set media url resolver on dash manifest', function () {
    $manifest = new DynamicDASHManifest;

    $resolver = fn ($filename) => Storage::disk('public')->url($filename);

    $result = $manifest->setMediaUrlResolver($resolver);

    expect($result)->toBe($manifest);
    expect($manifest->getMediaUrlResolver())->toBe($resolver);
});

it('can set init url resolver on dash manifest', function () {
    $manifest = new DynamicDASHManifest;

    $resolver = fn ($filename) => "https://cdn.example.com/init/{$filename}";

    $result = $manifest->setInitUrlResolver($resolver);

    expect($result)->toBe($manifest);
    expect($manifest->getInitUrlResolver())->toBe($resolver);
});

it('can chain resolver setters on dash manifest', function () {
    $manifest = new DynamicDASHManifest;

    $result = $manifest
        ->setMediaUrlResolver(fn ($file) => "media/{$file}")
        ->setInitUrlResolver(fn ($file) => "init/{$file}");

    expect($result)->toBe($manifest);
    expect($manifest->getMediaUrlResolver())->not->toBeNull();
    expect($manifest->getInitUrlResolver())->not->toBeNull();
});

it('clears cache when setting new resolver on dash manifest', function () {
    $manifest = new DynamicDASHManifest;

    $manifest->setMediaUrlResolver(fn ($file) => "url1-{$file}");
    $manifest->setMediaUrlResolver(fn ($file) => "url2-{$file}");

    expect($manifest->getMediaUrlResolver())->not->toBeNull();
});

it('can parse hls playlist lines', function () {
    $lines = "#EXTM3U\n#EXT-X-VERSION:3\nvideo.ts";

    $parsed = DynamicHLSPlaylist::parseLines($lines);

    expect($parsed)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($parsed->count())->toBe(3);
});

# Laravel Shaka Streamer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-streamer.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-streamer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/foxws/laravel-streamer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/foxws/laravel-streamer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/foxws/laravel-streamer/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/foxws/laravel-streamer/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-streamer.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-streamer)

A Laravel integration for [Google's Shaka Streamer](https://github.com/shaka-project/shaka-streamer), enabling you to package adaptive streaming content (HLS, DASH) with a fluent, Laravel-style API.

```php
use Foxws\Streamer\Facades\Streamer;

Streamer::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video.mp4')
    ->addAudioStream('videos/input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withSegmentDuration(6)
    ->export()
    ->toDisk('export')
    ->save();
```

## Features

- 🎬 **Fluent API** — Laravel-style chainable methods for packaging media
- 📁 **Filesystem Integration** — Read from and write to any Laravel disk (local, S3, etc.)
- 🎯 **Adaptive Bitrate** — Create multi-quality HLS & DASH streams
- 🔒 **AES Encryption** — Built-in content protection with optional key rotation
- 📺 **Dynamic Manifests** — Rewrite HLS playlists and DASH MPDs with signed URLs at serve-time
- 📡 **Events** — Hooks for `StreamingStarted`, `StreamingCompleted`, and `StreamingFailed`
- 📝 **PHP 8.3+** — Strict types, readonly properties, and modern PHP throughout

## Documentation

- [Quick Reference](docs/QUICK_REFERENCE.md) — Complete API at a glance
- [Configuration](docs/CONFIGURATION.md) — Environment variables and config options
- [AES Encryption](docs/AES_ENCRYPTION.md) — Encryption with key rotation
- [URL Resolvers](docs/URL_RESOLVERS.md) — Signed URLs for HLS & DASH
- [Troubleshooting](docs/TROUBLESHOOTING.md) — Common issues and solutions

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- [Shaka Streamer](https://github.com/shaka-project/shaka-streamer) binary (`pip install shaka-streamer`)

## Installation

```bash
composer require foxws/laravel-streamer
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="streamer-config"
```

Verify the binary is accessible:

```bash
php artisan streamer:info
```

## Quick Start

### Basic Packaging

```php
use Foxws\Streamer\Facades\Streamer;

Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->save();
```

### Cross-Disk Workflows

Read from one disk, write to another:

```php
Streamer::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video.mp4')
    ->addAudioStream('videos/input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->toDisk('export')
    ->toPath('streams/')
    ->withVisibility('public')
    ->save();
```

### Encryption

```php
// AES-128 encryption with auto-generated key
Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withAESEncryption()
    ->export()
    ->save();

// With key rotation (rotates every 60 seconds)
Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withAESEncryption('key', 'cenc')
    ->withKeyRotationDuration(60)
    ->export()
    ->toDisk('s3')
    ->save();
```

See the [AES Encryption Guide](docs/AES_ENCRYPTION.md) for protection schemes, codec-specific examples, and key management.

### Dynamic URL Resolvers

Serve HLS and DASH content with signed URLs — useful for S3, CDNs, or multi-tenant apps.

**HLS:**

```php
use Foxws\Streamer\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

return (new DynamicHLSPlaylist('s3'))
    ->open("videos/{$video->id}/master.m3u8")
    ->setKeyUrlResolver(fn (string $key) => Storage::disk('s3')->temporaryUrl(
        "videos/{$video->id}/{$key}",
        now()->addHour(),
    ))
    ->setMediaUrlResolver(fn (string $file) => Storage::disk('s3')->temporaryUrl(
        "videos/{$video->id}/{$file}",
        now()->addHours(2),
    ))
    ->setPlaylistUrlResolver(fn (string $playlist) => route('video.playlist', [
        'video' => $video,
        'playlist' => $playlist,
    ]))
    ->toResponse(request());
```

**DASH:**

```php
use Foxws\Streamer\Http\DynamicDASHManifest;
use Illuminate\Support\Facades\Storage;

return (new DynamicDASHManifest('s3'))
    ->open("videos/{$video->id}/manifest.mpd")
    ->setMediaUrlResolver(fn (string $file) => Storage::disk('s3')->temporaryUrl(
        "videos/{$video->id}/{$file}",
        now()->addHours(2),
    ))
    ->setInitUrlResolver(fn (string $file) => Storage::disk('s3')->temporaryUrl(
        "videos/{$video->id}/{$file}",
        now()->addHours(2),
    ))
    ->toResponse(request());
```

See [URL Resolvers](docs/URL_RESOLVERS.md) for more details.

### Events

Listen to streaming lifecycle events:

| Event                | Payload                                              |
| -------------------- | ---------------------------------------------------- |
| `StreamingStarted`   | `MediaCollection $mediaCollection`, `array $options` |
| `StreamingCompleted` | `StreamerResult $result`, `float $executionTime`     |
| `StreamingFailed`    | `Exception $exception`, `float $executionTime`       |

### Post-Export Inspection

After saving, you can inspect the result:

```php
$exporter = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export();

$exporter->afterSaving(function ($exporter, $result) {
    $summary = $exporter->getCopySummary();
    // ['total' => 12, 'copied' => 12, 'failed' => 0, 'totalSize' => 8421376]

    $keys = $result->getUploadedEncryptionKeys();
});

$exporter->toDisk('s3')->save();
```

## API Reference

### `Streamer` Facade → `MediaOpener`

| Method                               | Description                                      |
| ------------------------------------ | ------------------------------------------------ |
| `fromDisk($disk)`                    | Set the source filesystem disk                   |
| `open($paths)`                       | Open one or more media files                     |
| `openFromDisk($disk, $paths)`        | Set disk and open files in one call              |
| `get()`                              | Get the `MediaCollection`                        |
| `export()`                           | Start the export chain (returns `MediaExporter`) |
| `dynamicHLSPlaylist(?string $disk)`  | Create a `DynamicHLSPlaylist` instance           |
| `dynamicDASHManifest(?string $disk)` | Create a `DynamicDASHManifest` instance          |
| `cleanupTemporaryFiles()`            | Delete all temporary directories                 |

### Stream Configuration (via `MediaOpener` → `Streamer`)

| Method                                             | Description                                                     |
| -------------------------------------------------- | --------------------------------------------------------------- |
| `addVideoStream($input, $output, $options)`        | Add a video stream                                              |
| `addAudioStream($input, $output, $options)`        | Add an audio stream                                             |
| `addTextStream($input, $output, $options)`         | Add a text/subtitle stream                                      |
| `addStream(array $stream)`                         | Add a raw Shaka stream descriptor (`in`, `stream`, `output`, …) |
| `withHlsMasterPlaylist($path)`                     | Set HLS output                                                  |
| `withMpdOutput($path)`                             | Set DASH/MPD output                                             |
| `withSegmentDuration(int $seconds)`                | Set segment duration                                            |
| `withManifestFormat(array $formats)`               | Set manifest formats (e.g. `['dash', 'hls']`)                   |
| `withResolutions(array $resolutions)`              | Set encoding resolutions                                        |
| `withVideoCodecs(array $codecs)`                   | Set video codecs (e.g. `['h264', 'hw:vp9']`)                    |
| `withAudioCodecs(array $codecs)`                   | Set audio codecs (e.g. `['aac', 'opus']`)                       |
| `withSegmentPerFile(bool $enabled)`                | Enable segment-per-file output                                  |
| `withLowLatencyDashMode(bool $enabled)`            | Enable low-latency DASH                                         |
| `withStreamingMode(string $mode)`                  | Set mode (`'vod'` or `'live'`)                                  |
| `withEncryption(array $config)`                    | Set raw encryption config                                       |
| `withAESEncryption($keyFilename, $scheme, $label)` | Auto-generate AES encryption key                                |
| `withKeyRotationDuration(int $seconds)`            | Enable key rotation (requires `'cenc'` or `'cbcs'`)             |
| `withOption($key, $value)`                         | Set a custom pipeline option                                    |
| `withOptions(array $options)`                      | Set multiple custom pipeline options                            |
| `getCommand()`                                     | Get the built config array (for debugging)                      |

### `MediaExporter` (returned by `export()`)

| Method                               | Description                                   |
| ------------------------------------ | --------------------------------------------- |
| `toDisk($disk)`                      | Set target disk for output                    |
| `toPath(string $path)`               | Set target subdirectory                       |
| `withVisibility(string $visibility)` | Set file visibility (`'public'`, `'private'`) |
| `afterSaving(callable $callback)`    | Register post-save callback                   |
| `save(?string $path)`                | Execute packaging and copy files to disk      |
| `getCommand()`                       | Get the built config array                    |
| `dd()`                               | Dump config and die                           |
| `getCopySummary()`                   | Get `{total, copied, failed, totalSize}`      |
| `getCopiedFiles()`                   | Get array of successfully copied files        |
| `getFailedFiles()`                   | Get array of failed file copies               |
| `hasCopyFailures()`                  | Check if any files failed to copy             |

### `DynamicHLSPlaylist`

| Method                             | Description                                        |
| ---------------------------------- | -------------------------------------------------- |
| `open(string $path)`               | Open a playlist file                               |
| `setKeyUrlResolver(callable)`      | Resolve encryption key URLs                        |
| `setMediaUrlResolver(callable)`    | Resolve media segment URLs                         |
| `setPlaylistUrlResolver(callable)` | Resolve sub-playlist URLs                          |
| `get()`                            | Get processed playlist content                     |
| `all()`                            | Get all processed playlists (master + variants)    |
| `toResponse($request)`             | Return as `application/vnd.apple.mpegurl` response |

### `DynamicDASHManifest`

| Method                          | Description                               |
| ------------------------------- | ----------------------------------------- |
| `open(string $path)`            | Open a manifest file                      |
| `setMediaUrlResolver(callable)` | Resolve media segment URLs                |
| `setInitUrlResolver(callable)`  | Resolve initialization segment URLs       |
| `get()`                         | Get processed manifest content            |
| `toResponse($request)`          | Return as `application/dash+xml` response |

## Configuration

Key options in `config/streamer.php`:

| Option                     | Default                             | Description                                    |
| -------------------------- | ----------------------------------- | ---------------------------------------------- |
| `streamer.streamer_binary` | `'shaka-streamer'`                  | Path to the Shaka Streamer binary              |
| `timeout`                  | `14400` (4h)                        | Process timeout in seconds                     |
| `temporary_files_root`     | `storage_path('app/streamer/temp')` | Directory for temporary files                  |
| `cache_files_root`         | `'/dev/shm'`                        | Fast storage for small files (keys, manifests) |
| `log_channel`              | `null`                              | Log channel for streamer output                |

See [Configuration](docs/CONFIGURATION.md) for all options and environment variables.

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover a security vulnerability, please report it via a private channel (e.g. email or GitHub Security Advisories) rather than publicly disclosing it.

## Acknowledgments

This package was inspired by and learned from:

- [Laravel FFmpeg](https://github.com/protonemedia/laravel-ffmpeg) — Architecture patterns and Laravel integration approach
- [quasarstream/shaka-php](https://github.com/quasarstream/shaka-php) — Shaka Packager wrapper and command building patterns

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

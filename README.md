# Laravel Streamer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-streamer.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-streamer)
[![GitHub Tests Action Status](https://github.com/foxws/laravel-streamer/actions/workflows/tests.yml/badge.svg)](https://github.com/foxws/laravel-streamer/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-streamer.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-streamer)

Encode video into several qualities and package it into HLS and DASH with [Shaka Streamer](https://github.com/shaka-project/shaka-streamer), on top of [foxws/laravel-media](https://github.com/foxws/laravel-media). Read the source from any Laravel disk, and write the result to any disk.

See the [full documentation](docs): [Installation](docs/installation.md), [Usage](docs/usage.md), [Testing](docs/testing.md), [Configuration](docs/configuration.md), [Upgrading from 2.x](docs/upgrading.md).

## Requirements

- PHP 8.4 or higher
- Laravel 13
- [foxws/laravel-media](https://github.com/foxws/laravel-media) 0.3.4 or higher
- [Shaka Streamer](https://github.com/shaka-project/shaka-streamer), with FFmpeg and Shaka Packager

## Installation

```bash
composer require foxws/laravel-streamer
```

Install Shaka Streamer with its bundled FFmpeg and Shaka Packager, then check the setup:

```bash
pip install shaka-streamer shaka-streamer-binaries
php artisan media:info
```

See [Installation](docs/installation.md) to use your own FFmpeg and Shaka Packager instead.

## Quick start

```php
use Foxws\Media\Facades\Media;

$result = Media::fromDisk('media')
    ->open('videos/clip.mp4')
    ->streamer()
    ->addStreamsFrom()
    ->withResolutions('1080p', '720p', '480p')
    ->withHlsPlaylist()
    ->withDashManifest()
    ->toDisk('s3')
    ->save('streams/clip');
```

This encodes three qualities, writes one set of segments with both an HLS playlist and a DASH manifest, and uploads them to `streams/clip` on the `s3` disk. Run it in a queued job: encoding takes a while.

Serve private streams with laravel-media's signed playlists:

```php
return Media::fromDisk('s3')->open('streams/clip/master.m3u8')
    ->hlsPlaylist()
    ->resolveMediaUrlsUsing(fn (string $path) => Storage::disk('s3')->temporaryUrl($path, now()->addHour()))
    ->toResponse($request);
```

## Testing

```bash
composer test
```

## Links

- [CHANGELOG](CHANGELOG.md)
- [Security policy](../../security/policy)
- [foxws/laravel-media](https://github.com/foxws/laravel-media)
- [Shaka Streamer documentation](https://shaka-project.github.io/shaka-streamer/)

## Credits

- [francoism90](https://github.com/francoism90)
- [All Contributors](../../contributors)

Used by [Stry](https://github.com/francoism90/stry), a self-hosted video streaming app.

AI, specifically [Claude](https://claude.com/product/claude-code), was used to help build this package. All AI-assisted output is reviewed by me, and I retain final say over everything that is implemented and released.

## License

MIT. See [License File](LICENSE.md).

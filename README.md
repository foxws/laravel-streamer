# Laravel Streamer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-streamer.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-streamer)
[![GitHub Tests Action Status](https://github.com/foxws/laravel-streamer/actions/workflows/run-tests.yml/badge.svg)](https://github.com/foxws/laravel-streamer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/foxws/laravel-streamer/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/foxws/laravel-streamer/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-streamer.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-streamer)

Runs [Shaka Streamer](https://github.com/shaka-project/shaka-streamer) from Laravel. It encodes video into several qualities with FFmpeg, then packages them into HLS and DASH streams. Read the source from any Laravel disk, and write the result to any disk.

If your video is already encoded the way you want it, [Laravel Shaka](https://github.com/foxws/laravel-shaka) packages it without re-encoding, which is much faster.

See the [full documentation](docs): [Installation](docs/installation.md), [Usage](docs/usage.md), [URL Resolvers](docs/url-resolvers.md), [Queues](docs/queue-integration.md), [Encryption](docs/aes-encryption.md), [Configuration](docs/configuration.md), [Quick Reference](docs/quick-reference.md), [Troubleshooting](docs/troubleshooting.md).

## Requirements

- PHP 8.3 or higher
- Laravel 12 or 13
- [Shaka Streamer](https://github.com/shaka-project/shaka-streamer), with FFmpeg and Shaka Packager

## Installation

```bash
composer require foxws/laravel-streamer
```

```bash
php artisan vendor:publish --tag="streamer-config"
```

Install Shaka Streamer with its bundled FFmpeg and Shaka Packager, then check the setup:

```bash
pip install shaka-streamer shaka-streamer-binaries
php artisan streamer:info
```

See [Installation](docs/installation.md) to use your own FFmpeg and Shaka Packager instead.

## Quick start

```php
use Foxws\Streamer\Facades\Streamer;

$streamer = Streamer::fromDisk('media')->open('videos/clip.mp4');

try {
    $streamer
        ->addVideoStream('videos/clip.mp4', 'video.mp4')
        ->addAudioStream('videos/clip.mp4', 'audio.mp4')
        ->withResolutions(['1080p', '720p', '480p'])
        ->withMpdOutput('index.mpd')
        ->withHlsMasterPlaylist('master.m3u8')
        ->export()
        ->toDisk('s3')
        ->toPath('streams/clip/')
        ->save();
} finally {
    $streamer->cleanupTemporaryFiles();
}
```

This encodes three qualities, writes one set of segments with both a DASH manifest and an HLS playlist, and uploads them to `streams/clip/` on the `s3` disk. Run it in a [queued job](docs/queue-integration.md): encoding takes a while.

To serve a private stream, rewrite the playlist with signed URLs when it's requested:

```php
return Streamer::dynamicHLSPlaylist('s3')
    ->setMediaUrlResolver(fn (string $path) => Storage::disk('s3')->temporaryUrl("streams/clip/{$path}", now()->addHour()))
    ->open('streams/clip/master.m3u8')
    ->toResponse($request);
```

See [URL Resolvers](docs/url-resolvers.md) and [Encryption](docs/aes-encryption.md).

## Testing

```bash
composer test
```

## Links

- [CHANGELOG](CHANGELOG.md)
- [Security policy](../../security/policy)
- [Laravel Shaka](https://github.com/foxws/laravel-shaka), for packaging without re-encoding
- [Shaka Streamer documentation](https://shaka-project.github.io/shaka-streamer/)

## Credits

- [francoism90](https://github.com/francoism90)
- [All Contributors](../../contributors)

This package started from ideas in [Laravel FFMpeg](https://github.com/protonemedia/laravel-ffmpeg) and [shaka-php](https://github.com/quasarstream/shaka-php).

Used by [Stry](https://github.com/francoism90/stry), a self-hosted video streaming app.

AI, specifically [Claude](https://claude.com/product/claude-code), was used to help build this package. All AI-assisted output is reviewed by me, and I retain final say over everything that is implemented and released.

## License

MIT. See [License File](LICENSE.md).

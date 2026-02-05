# Laravel Shaka Streamer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxws/laravel-streamer.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-streamer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/foxws/laravel-streamer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/foxws/laravel-streamer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/foxws/laravel-streamer/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/foxws/laravel-streamer/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxws/laravel-streamer.svg?style=flat-square)](https://packagist.org/packages/foxws/laravel-streamer)

A Laravel integration for [Google's Shaka Streamer](https://github.com/shaka-project/shaka-streamer), enabling you to create adaptive streaming content (HLS, DASH) with a fluent, Laravel-style API.

```php
use Foxws\Streamer\Facades\Shaka;

$result = Streamer::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video_1080p.mp4', ['bandwidth' => '5000000'])
    ->addVideoStream('videos/input.mp4', 'video_720p.mp4', ['bandwidth' => '3000000'])
    ->addAudioStream('videos/input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withSegmentDuration(6)
    ->export()
    ->toDisk('export')
    ->save();
```

## Features

- 🎬 **Fluent API** - Laravel-style chainable methods
- 📁 **Multiple Disks** - Works with local, S3, and custom filesystems
- 🎯 **Adaptive Bitrate** - Create multi-quality streams easily
- 🔒 **Encryption & DRM** - Built-in support for content protection
- 📺 **HLS & DASH** - Support for both streaming protocols
- 🧪 **Testable** - Clean architecture with mockable components
- 📝 **Type-Safe** - Full PHP 8.1+ type declarations

## Documentation

📚 **[Full Documentation](docs/README.md)**

- [Quick Reference](docs/QUICK_REFERENCE.md) - Complete API reference
- [AES Encryption](docs/AES_ENCRYPTION.md) - Encryption with key rotation
- [Architecture Overview](docs/ARCHITECTURE.md) - Understanding the design
- [Configuration](docs/CONFIGURATION.md) - Configuring the package

## Requirements

- PHP 8.3 or higher
- Laravel 11.x or higher
- Shaka Streamer binary installed on your system or Docker container

## Installation

Install the package via composer:

```bash
composer require foxws/laravel-streamer
```

Publish the config file:

```bash
php artisan vendor:publish --tag="streamer-config"
```

### Installing Shaka Streamer

Install Shaka Streamer binary on your system. Visit the [Shaka Streamer releases](https://github.com/shaka-project/shaka-streamer/releases) page for installation instructions.

### Verify Installation

After installation, verify that Shaka Streamer is properly configured:

```bash
php artisan streamer:verify
```

This will check:

- Binary exists and is executable
- Can retrieve version information
- Configuration is properly set up
- Temporary directory is accessible

### Package Information

View package and binary information:

```bash
php artisan streamer:info
```

## Quick Start

### Basic Usage

```php
use Foxws\Streamer\Facades\Shaka;

$result = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->save();
```

### Adaptive Bitrate Streaming

```php
$result = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video_1080p.mp4', ['bandwidth' => '5000000'])
    ->addVideoStream('input.mp4', 'video_720p.mp4', ['bandwidth' => '3000000'])
    ->addVideoStream('input.mp4', 'video_480p.mp4', ['bandwidth' => '1500000'])
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withSegmentDuration(6)
    ->export()
    ->save();
```

### Working with Different Disks

```php
$result = Streamer::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video.mp4')
    ->addAudioStream('videos/input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->toDisk('export') // Save output to a different disk (e.g., local, s3, etc.)
    ->toPath('exports/') // (Optional) Save to a subdirectory on the target disk
    ->save();
```

### HLS with Encryption

```php
// Basic encryption with auto-generated AES-128 key
Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withAESEncryption()  // Auto-generates key with 'cbc1' scheme
    ->export()
    ->save();

// With key rotation (generates key_0.key, key_1.key, etc.)
Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withAESEncryption()
    ->withKeyRotationDuration(60)  // Rotate every 60 seconds
    ->export()
    ->toDisk('s3')
    ->save();
```

See [AES Encryption Guide](docs/AES_ENCRYPTION.md) for complete documentation.

### Dynamic URL Resolvers (HLS & DASH)

Serve encrypted streaming content with S3 signed URLs:

**HLS Example:**

```php
use Foxws\Streamer\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

public function playlist(Video $video)
{
    return (new DynamicHLSPlaylist('s3'))
        ->open("videos/{$video->id}/master.m3u8")
        ->setKeyUrlResolver(fn ($key) => Storage::disk('s3')->temporaryUrl(
            "videos/{$video->id}/{$key}",
            now()->addHour()
        ))
        ->setMediaUrlResolver(fn ($file) => Storage::disk('s3')->temporaryUrl(
            "videos/{$video->id}/{$file}",
            now()->addHours(2)
        ))
        ->toResponse(request());
}
```

**DASH Example:**

```php
use Foxws\Streamer\Http\DynamicDASHManifest;
use Illuminate\Support\Facades\Storage;

public function manifest(Video $video)
{
    return (new DynamicDASHManifest('s3'))
        ->open("videos/{$video->id}/manifest.mpd")
        ->setKeyUrlResolver(fn ($key) => Storage::disk('s3')->temporaryUrl(
            "videos/{$video->id}/{$key}",
            now()->addHour()
        ))
        ->setMediaUrlResolver(fn ($file) => Storage::disk('s3')->temporaryUrl(
            "videos/{$video->id}/{$file}",
            now()->addHours(2)
        ))
        ->setInitUrlResolver(fn ($file) => Storage::disk('s3')->temporaryUrl(
            "videos/{$video->id}/{$file}",
            now()->addHours(2)
        ))
        ->toResponse(request());
}
```

**Use cases for URL resolvers:**

- 🔐 Generate signed URLs for secure content delivery
- 🌐 Integrate with CDN services
- 🏢 Support multi-tenant applications
- 🔄 Implement dynamic key rotation
- 📊 Track media access patterns

See [URL Resolver Examples](examples/UrlResolverExamples.php) and [Documentation](docs/URL_RESOLVERS.md) for more details.

## Available Methods

### Disk Management

- `fromDisk(string $disk)` - Set the disk to use
- `openFromDisk(string $disk, $paths)` - Set disk and open files in one call
- `getDisk()` - Get the current disk instance

### Media Management

- `open($paths)` - Open one or more media files
- `get()` - Get the MediaCollection
- `streams()` - Get auto-generated Stream objects

### Stream Configuration

- `addVideoStream(string $input, string $output, array $options = [])` - Add video stream
- `addAudioStream(string $input, string $output, array $options = [])` - Add audio stream
- `addTextStream(string $input, string $output, array $options = [])` - Add text/caption/subtitle stream
- `addStream(array $stream)` - Add custom stream

### Output Configuration

- `withHlsMasterPlaylist(string $path)` - Set HLS master playlist output
- `withMpdOutput(string $path)` - Set DASH manifest output
- `withSegmentDuration(int $seconds)` - Set segment duration
- `withAESEncryption(string $keyFilename = 'key', ?string $protectionScheme = 'cbc1', ?string $label = null)` - Enable AES-128 encryption
- `withKeyRotationDuration(int $seconds)` - Enable key rotation for encryption
- `toDisk(string $disk)` - Set the target disk for output
- `toPath(string $path)` - Set the target output path (subdirectory)
- `withVisibility(string $visibility)` - Set file visibility (e.g., 'public', 'private')

### Execution & Utilities

- `export()` - Execute the packaging operation (returns result object)
- `save(?string $path = null)` - Save outputs to disk (optionally to a specific path)
- `getCommand()` - Get the final command string (for debugging)
- `dd()` - Dump the final command and end the script
- `afterSaving(callable $callback)` - Register a callback to run after saving

### Dynamic URL Resolvers

**DynamicHLSPlaylist:**

- `new DynamicHLSPlaylist(?string $disk)` - Create HLS playlist processor
- `open(string $path)` - Open a playlist file
- `setKeyUrlResolver(callable $resolver)` - Set resolver for encryption key URLs
- `setMediaUrlResolver(callable $resolver)` - Set resolver for media segment URLs
- `setPlaylistUrlResolver(callable $resolver)` - Set resolver for sub-playlist URLs
- `get()` - Get processed playlist content
- `all()` - Get all processed playlists (master + segments)
- `toResponse($request)` - Return as HTTP response

**DynamicDASHManifest:**

- `new DynamicDASHManifest(?string $disk)` - Create DASH manifest processor
- `open(string $path)` - Open a manifest file
- `setMediaUrlResolver(callable $resolver)` - Set resolver for media segment URLs
- `setInitUrlResolver(callable $resolver)` - Set resolver for initialization segment URLs
- `get()` - Get processed manifest content
- `toResponse($request)` - Return as HTTP response

See the [Quick Reference](docs/QUICK_REFERENCE.md) for complete API documentation.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

If you discover a security vulnerability, please report it via a private channel (e.g., email or GitHub issues) rather than publicly disclosing it.

## Acknowledgments

This package was inspired by and learned from:

- [Laravel FFmpeg](https://github.com/protonemedia/laravel-ffmpeg) - Architecture patterns and Laravel integration approach.
- [quasarstream/shaka-php](https://github.com/quasarstream/shaka-php) - Shaka Streamer wrapper implementation and command building logic.

Much of the existing logic and design patterns from these excellent packages helped shape this implementation. Many thanks to their authors and contributors!

## Projects Built on Laravel Shaka Streamer

- [Stry](https://github.com/francoism90/stry) - A modern streaming platform built on top of Laravel Shaka Streamer.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

# Laravel Shaka Streamer Documentation

Welcome to the Laravel Shaka Streamer documentation. This package provides a Laravel integration for Google's Shaka Streamer, enabling you to create adaptive streaming content (DASH, HLS) with a fluent, Laravel-style API.

## Getting Started

- [Installation & Setup](#installation--setup)
- [Quick Start Guide](#quick-start-guide)
- [Basic Concepts](#basic-concepts)

## Documentation

### Core Documentation

- **[Quick Reference](QUICK_REFERENCE.md)** - Complete API reference with examples
- **[Architecture Overview](ARCHITECTURE.md)** - Understanding the binary driver architecture
- **[Configuration](CONFIGURATION.md)** - Configuring the package

### Guides

- **[Queue Integration](QUEUE_INTEGRATION.md)** - Process media in background queues
- **[Troubleshooting](TROUBLESHOOTING.md)** - Common issues and solutions
- **[URL Resolvers](URL_RESOLVERS.md)** - Dynamic URL customization for CDN/signed URLs
- **[Working with Different Disks](guides/WORKING_WITH_DISKS.md)** - Using local, S3, and custom filesystems
- **[Adaptive Bitrate Streaming](guides/ADAPTIVE_BITRATE.md)** - Creating multi-quality streams
- **[Encryption & DRM](guides/ENCRYPTION.md)** - Securing your content
- **[HLS Packaging](guides/HLS_PACKAGING.md)** - Creating HLS streams
- **[DASH Packaging](guides/DASH_PACKAGING.md)** - Creating DASH streams

### Examples

All examples are available in the `examples/` directory:

- [Fluent Builder Examples](../examples/FluentBuilderExamples.php)
- [From Disk Examples](../examples/FromDiskExamples.php)
- [Streamer Examples](../examples/StreamerExamples.php)

## Installation & Setup

### Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x or higher
- Shaka Streamer Python package installed

### Install the Package

```bash
composer require foxws/laravel-streamer
```

### Publish Configuration

```bash
php artisan vendor:publish --tag="streamer-config"
```

### Verify Installation

```bash
php artisan streamer:verify
```

This command checks:

- Binary exists and is executable
- Can retrieve version information
- Configuration is properly set up
- Logger status

## Quick Start Guide

### Basic Usage

```php
use Foxws\Streamer\Facades\Shaka;

$result = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->export()
    ->toDisk('export')
    ->toPath('custom/subdir')
    ->save();
```

### Using Different Disks

```php
$result = Streamer::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video.mp4')
    ->addAudioStream('videos/input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->export()
    ->toDisk('export')
    ->save();
```

### Adaptive Bitrate Streaming

```php
$result = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video_1080p.mp4', [
        'bandwidth' => '5000000',
    ])
    ->addVideoStream('input.mp4', 'video_720p.mp4', [
        'bandwidth' => '3000000',
    ])
    ->addVideoStream('input.mp4', 'video_480p.mp4', [
        'bandwidth' => '1500000',
    ])
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->withSegmentDuration(6)
    ->export()
    ->toDisk('export')
    ->save();
```

## Basic Concepts

### Architecture

Laravel Shaka follows a clean driver-based architecture similar to PHP-FFmpeg:

- **Driver Layer** (`ShakaStreamer`) - Handles binary interaction
- **Business Logic Layer** (`Streamer`) - Provides high-level API
- **Facade Layer** (`Shaka`) - Laravel-style fluent interface

### Key Components

- **Media & MediaCollection** - Represents input files
- **Stream** - Represents output streams (video/audio)
- **CommandBuilder** - Fluently builds streamer commands
- **StreamerResult** - Structured result from operations

### Workflow

1. **Open** - Load media files from disk
2. **Configure** - Add streams and set options
3. **Export** - Execute the packaging operation

## Support & Contributing

- [GitHub Repository](https://github.com/foxws/laravel-streamer)
- [Issue Tracker](https://github.com/foxws/laravel-streamer/issues)
- [Contributing Guidelines](../CONTRIBUTING.md)

## License

Laravel Shaka is open-sourced software licensed under the [MIT license](../LICENSE.md).

---
section: Getting Started
order: 1
---

# Installation

## Install the package

```bash
composer require foxws/laravel-streamer
```

This also installs [foxws/laravel-media](https://github.com/foxws/laravel-media), which opens the media, runs Shaka Streamer and saves the result. Its own settings (temporary files, logging, disks, the ffmpeg and ffprobe paths) live in `config/media.php`.

Publish the config file to change the defaults:

```bash
php artisan vendor:publish --tag="streamer-config"
```

This creates `config/streamer.php`. See [Configuration](configuration.md) for every option.

## Install Shaka Streamer

Shaka Streamer is a Python tool. It needs FFmpeg and Shaka Packager to do the actual work. There are two ways to get them.

**With the bundled binaries.** The `shaka-streamer-binaries` package ships matching FFmpeg and Shaka Packager builds:

```bash
pip install shaka-streamer shaka-streamer-binaries
```

**With your own binaries.** Install `ffmpeg` and `packager` yourself, for example to use an FFmpeg build with hardware encoding:

```bash
pip install shaka-streamer
```

Then turn on system binaries, so Shaka Streamer runs the `ffmpeg` and `packager` on your `PATH`:

```env
STREAMER_SYSTEM_BINARIES=true
```

The directories of laravel-media's ffmpeg and ffprobe (`MEDIA_FFMPEG_PATH`, `MEDIA_FFPROBE_PATH`) go first in that `PATH`, so Shaka Streamer uses the same FFmpeg as the rest of your app. Set `STREAMER_PACKAGER_BINARY` to a full path when `packager` isn't on your `PATH`.

If `shaka-streamer` isn't on your `PATH`, set its location:

```env
STREAMER_BINARY=/opt/venv/bin/shaka-streamer
```

## Check the setup

```bash
php artisan media:info
```

This lists `shaka-streamer` next to ffmpeg and ffprobe, with the path and version it found. `php artisan about` shows the same paths.

## AI agents

The package includes a [Laravel Boost](https://github.com/laravel/boost) skill. Run `php artisan boost:install` (or `boost:update`) after installing, and your AI agent learns how to encode, package and test with it.

Next: [Usage](usage.md).

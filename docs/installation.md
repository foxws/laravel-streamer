---
section: Getting Started
order: 1
---

# Installation

## Install the package

```bash
composer require foxws/laravel-streamer
```

Publish the config file:

```bash
php artisan vendor:publish --tag="streamer-config"
```

This creates `config/streamer.php`. See [Configuration](configuration.md) for every option.

To upload to S3, also install the Flysystem S3 adapter if your app doesn't have it yet:

```bash
composer require league/flysystem-aws-s3-v3
```

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

Then call `useSystemBinaries()` on every job, so Shaka Streamer uses the `ffmpeg` and `packager` on your `PATH`. See [Usage](usage.md).

If `shaka-streamer` isn't on your `PATH`, set its location:

```env
STREAMER_BINARY=/opt/venv/bin/shaka-streamer
```

## Check the setup

```bash
php artisan streamer:info
```

This runs `shaka-streamer --version` and shows the binary, its version, the timeout, the temporary directory and the log channel. It fails if the binary can't run or the temporary directory isn't writable.

It doesn't check FFmpeg or Shaka Packager. Run `ffmpeg -version` and `packager --version` yourself when you use system binaries.

## Laravel Boost

The package includes a [Laravel Boost](https://github.com/laravel/boost) skill. Run `php artisan boost:install` (or `boost:update`) after installing, and your AI agent learns how to encode, package and serve streams with it.

Next: [Usage](usage.md).

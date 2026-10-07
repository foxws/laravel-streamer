---
title: Introduction
metadata:
  role: Media
  group: media
  eyebrow: "Video · HLS/DASH · Shaka Streamer"
  desc: "Encode and package video into HLS and DASH streams with Shaka Streamer."
  lead: "Encode a video into several qualities and codecs, and package them into HLS and DASH in one run. Read from any Laravel disk, and write to any disk."
  requires: "PHP ^8.4"
  laravel: "13.x"
  runtime: "Shaka Streamer, FFmpeg, Shaka Packager, foxws/laravel-media"
  licence: MIT
  used_by:
    - name: Stry
      desc: "A self-hosted video streaming app."
      href: "https://github.com/francoism90/stry"
---

# Introduction

This package runs [Shaka Streamer](https://github.com/shaka-project/shaka-streamer) from Laravel. Shaka Streamer encodes a video into several qualities with FFmpeg, then packages them into HLS and DASH streams with Shaka Packager.

It's an add-on for [foxws/laravel-media](https://github.com/foxws/laravel-media): you open media with laravel-media, and `streamer()` encodes and packages it.

```php
use Foxws\Media\Facades\Media;

Media::fromDisk('media')
    ->open('videos/clip.mp4')
    ->streamer()
    ->addStreamsFrom()
    ->withResolutions('1080p', '720p', '480p')
    ->withHlsPlaylist()
    ->withDashManifest()
    ->toDisk('s3')
    ->save('streams/clip');
```

## Streamer, ladder or Shaka?

Encoding is slow: it can take longer than the video itself, unless you have hardware encoding.

- **laravel-streamer** encodes and packages in one step, with Shaka Streamer's codec and resolution presets.
- **laravel-media's ladder** encodes the qualities with one FFmpeg run, with your own bitrates and aligned keyframes. Package them with laravel-media's packager, or with [laravel-shaka](https://github.com/foxws/laravel-shaka).
- If your video is already encoded the way you want it, package it without re-encoding. That's much faster, and the output is about the size of the input.

## Features

- Encode to several resolutions and codecs (H.264, HEVC, VP9, AV1, AAC, Opus), with optional hardware encoding.
- Build DASH and HLS from the same segments in one run.
- Encrypt with AES (`cenc` or `cbcs`) and a generated key.
- Read input from, and write output to, any Laravel disk, with laravel-media's concurrent S3 uploads, signed playlists, events and temporary file cleanup.
- `Media::fake()` in tests, with `FakeStreamer` writing placeholder outputs.

## Requirements

- PHP 8.4 or higher
- Laravel 13
- [foxws/laravel-media](https://github.com/foxws/laravel-media) 0.3.4 or higher
- [Shaka Streamer](https://github.com/shaka-project/shaka-streamer), with FFmpeg and Shaka Packager

## Pages

- [Installation](installation.md)
- [Usage](usage.md)
- [Testing](testing.md)
- [Configuration](configuration.md)
- [Upgrading from 2.x](upgrading.md)

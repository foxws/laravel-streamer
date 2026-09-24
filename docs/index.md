---
title: Introduction
metadata:
  role: Media
  eyebrow: "Video · HLS/DASH · Shaka Streamer"
  desc: "Encode and package video into HLS and DASH streams with a fluent Laravel API."
  requires: "PHP ^8.3"
  laravel: "12.x / 13.x"
  runtime: "Shaka Streamer, FFmpeg, Shaka Packager"
  licence: MIT
  used_by:
    name: Stry
    desc: "A self-hosted video streaming app."
    href: "https://github.com/francoism90/stry"
---

# Introduction

This package runs [Shaka Streamer](https://github.com/shaka-project/shaka-streamer) from Laravel. Shaka Streamer encodes a video into several qualities with FFmpeg, then packages them into HLS and DASH streams with Shaka Packager. Read the source from any Laravel disk, and write the result to any disk.

```php
use Foxws\Streamer\Facades\Streamer;

Streamer::fromDisk('media')
    ->open('videos/clip.mp4')
    ->addVideoStream('videos/clip.mp4', 'video.mp4')
    ->addAudioStream('videos/clip.mp4', 'audio.mp4')
    ->withResolutions(['1080p', '720p', '480p'])
    ->withMpdOutput('index.mpd')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->toDisk('s3')
    ->toPath('streams/clip/')
    ->save();
```

## Streamer or Shaka?

Encoding is slow: it can take longer than the video itself, unless you have hardware encoding. Use this package when you need several qualities or a different codec.

If your video is already encoded the way you want it, [Laravel Shaka](https://github.com/foxws/laravel-shaka) packages it without re-encoding. That's much faster, and the output is about the size of the input.

## Features

- Encode to several resolutions and codecs, with optional hardware encoding.
- Build DASH and HLS from the same segments in one run.
- Encrypt with AES and a generated key.
- Serve private streams by signing every URL in a playlist when it's requested.
- Upload to S3 in parallel, with multipart uploads for large files.

## Requirements

- PHP 8.3 or higher
- Laravel 12 or 13
- Shaka Streamer, with FFmpeg and Shaka Packager

## Pages

- [Installation](installation.md)
- [Usage](usage.md)
- [URL Resolvers](url-resolvers.md)
- [Queues](queue-integration.md)
- [Encryption](aes-encryption.md)
- [Configuration](configuration.md)
- [Quick Reference](quick-reference.md)
- [Troubleshooting](troubleshooting.md)

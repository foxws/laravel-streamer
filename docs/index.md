---
title: Introduction
metadata:
  role: Media
  eyebrow: "Video · HLS/DASH · Shaka Streamer"
  desc: "Package adaptive streaming video with Google's Shaka Streamer, Laravel-style."
  requires: "PHP ^8.3"
  laravel: "12.x / 13.x"
  licence: MIT
---

# Introduction

This package connects Laravel to [Google's Shaka Streamer](https://github.com/shaka-project/shaka-streamer). Shaka Streamer takes a video file and packages it for adaptive streaming — HLS, DASH, or both. This package wraps that tool in a fluent, chainable API that feels like the rest of Laravel.

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

## What it does

| Feature | What it means |
| --- | --- |
| Fluent API | Chainable, Laravel-style methods for packaging media |
| Filesystem integration | Read from and write to any Laravel disk (local, S3, and so on) |
| Adaptive bitrate | Create multi-quality HLS and DASH streams |
| CMAF by default | DASH and HLS are generated from the same fragmented-MP4 segments in one run |
| AES encryption | Built-in content protection, with optional key rotation |
| Dynamic manifests | Rewrite HLS playlists and DASH manifests with signed URLs when serving them |
| Events | Hooks for `StreamingStarted`, `StreamingCompleted`, and `StreamingFailed` |
| Modern PHP | Built for PHP 8.3+, using strict types and readonly properties |

## Where to go next

- [Installation](./installation.md) — requirements and setup
- [Usage](./usage.md) — the main API walkthrough
- [Quick Reference](./quick-reference.md) — condensed method and pattern reference
- [Configuration](./configuration.md) — environment variables and config options
- [AES Encryption](./aes-encryption.md) — encryption with key rotation
- [URL Resolvers](./url-resolvers.md) — dynamic, signed URLs for HLS & DASH
- [Queue Integration](./queue-integration.md) — processing media in background jobs
- [Troubleshooting](./troubleshooting.md) — common issues and solutions

Start with [Installation](./installation.md).

---
sidebar_position: 1
---

# Introduction

A Laravel integration for [Google's Shaka Streamer](https://github.com/shaka-project/shaka-streamer),
enabling you to package adaptive streaming content (HLS, DASH) with a
fluent, Laravel-style API.

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

- **Fluent API** — Laravel-style chainable methods for packaging media
- **Filesystem Integration** — Read from and write to any Laravel disk (local, S3, etc.)
- **Adaptive Bitrate** — Create multi-quality HLS & DASH streams
- **AES Encryption** — Built-in content protection with optional key rotation
- **Dynamic Manifests** — Rewrite HLS playlists and DASH MPDs with signed URLs at serve-time
- **Events** — Hooks for `StreamingStarted`, `StreamingCompleted`, and `StreamingFailed`
- **PHP 8.3+** — Strict types, readonly properties, and modern PHP throughout

## See also

- [Installation](./installation.md) — requirements and setup
- [Usage](./usage.md) — the main API walkthrough
- [Quick Reference](./quick-reference.md) — condensed method and pattern reference
- [Configuration](./configuration.md) — environment variables and config options
- [AES Encryption](./aes-encryption.md) — encryption with key rotation
- [URL Resolvers](./url-resolvers.md) — dynamic, signed URLs for HLS & DASH
- [Queue Integration](./queue-integration.md) — processing media in background jobs
- [Troubleshooting](./troubleshooting.md) — common issues and solutions

Continue to [Installation](./installation.md) to get started.

---
name: laravel-streamer-development
description: Encode video into several resolutions and codecs and package it into HLS and DASH with foxws/laravel-streamer (Shaka Streamer) on top of foxws/laravel-media, including resolutions from a laravel-media ladder, hardware encoding, system binaries, cenc/cbcs encryption, subtitles, exporting to local or S3 disks and faking Shaka Streamer in tests. Use when working with $opener->streamer(), Foxws\Streamer classes, config/streamer.php, or building adaptive bitrate streams in one step.
---

# Encoding and packaging with laravel-streamer

`foxws/laravel-streamer` is an add-on for `foxws/laravel-media`. It runs [Shaka Streamer](https://shaka-project.github.io/shaka-streamer/), which encodes every stream into the chosen resolutions and codecs with FFmpeg, then packages them into HLS and DASH with Shaka Packager. Opening media, disks, temporary files, the process runner, uploads, encryption keys, signed playlists, events and fakes all come from laravel-media; activate `laravel-media-development` for those.

## Flow

```php
use Foxws\Media\Encryption\ProtectionScheme;
use Foxws\Media\Facades\Media;
use Foxws\Media\Filesystem\ExportResult;
use Foxws\Streamer\StreamerBuilder;

$result = Media::fromDisk('media')
    ->open('videos/clip.mp4')
    ->streamer()
    ->addStreamsFrom()                      // or addVideoStream() / addAudioStream(language: 'en')
    ->addTextStream('captions/clip.en.vtt', 'en')
    ->withResolutions('1080p', '720p', '480p')
    ->withVideoCodecs('h264')
    ->withHlsPlaylist('master.m3u8')
    ->withDashManifest('manifest.mpd')
    ->withEncryption(scheme: ProtectionScheme::Cbcs)
    ->toDisk('streams')                     // defaults to the source disk
    ->withContext(['video_id' => $video->id])
    ->afterSaving(fn (StreamerBuilder $builder, ExportResult $result) => $video->markAsStreamable())
    ->save("{$video->id}");

$result->paths();          // manifests first
$result->encryptionKey();
```

- `save($directory)` writes the input and pipeline configs to a cache directory, runs `shaka-streamer -i -p -o` into a laravel-media temporary directory, checks the manifests exist, then moves or uploads everything. It dispatches `ExportCompleted`/`ExportFailed` with the `withContext()` data.
- At least one manifest is required. Shaka Streamer names the segment files itself.
- Don't call any cleanup: laravel-media deletes temporary files after every queue job and request.
- `add*Stream($path, ..., $options)`: `$path` defaults to the first opened file; other paths are read from the same disk. `$options` are Shaka Streamer input fields (`track_num`, `start_time`, `extra_input_args`, ...).

## Qualities and codecs

- Resolution names: `144p` ... `1440p`, `4k`, `8k` (plus `-hfr`). Shaka Streamer skips resolutions above the source.
- `withLadder(Ladder::standard())` maps a laravel-media ladder's heights to those names and its codec to `h264`/`hevc`/`av1`; Shaka Streamer picks the bitrates. Heights without a name throw `InvalidArgumentException`.
- `withVideoCodecs('h264', 'vp9', 'av1', 'hevc')`, `withAudioCodecs('aac', 'opus')`, `withChannelLayouts('stereo')`. Each codec is a separate encode.
- Hardware: `->withVideoCodecs('hw:h264')->withHardwareAcceleration('vaapi')`, usually with system binaries (`useSystemBinaries()` or `STREAMER_SYSTEM_BINARIES=true`), which put laravel-media's ffmpeg/ffprobe directories and `streamer.packager` first in the PATH.
- Other pipeline fields: `segmentDuration()`, `segmentPerFile()`, `withTrickPlay()`, `lowLatencyDashMode()`, `withFFmpegInputArgs()`, and `withOption('name', $value)` for anything else (`null` removes).
- Prefer laravel-media's own ladder (`$opener->ladder()`, then package) when you need your own bitrates or aligned keyframes; this package is for Shaka Streamer's presets in one step.

## Encryption

`withEncryption(?EncryptionKey $key, ?ProtectionScheme $scheme, ?string $keyFile = 'key', ?string $keyUri, ?string $label, float $clearLead = 0)` uses raw-key encryption with `cenc` or `cbcs` only (others throw). The raw key is saved next to the segments as the key file, and HLS playlists point to it. No key rotation: use laravel-shaka for that. Serve with laravel-media's `hlsPlaylist()->resolveKeyUrlsUsing(...)`.

## Queues and errors

- Encoding is slow; run it in a queued job with `->timeout($seconds)` below the job's `$timeout` (default `streamer.timeout`).
- Failures throw `Foxws\Media\Exceptions\ProcessFailedException` (use `isRetryable()`); a missing binary throws `ExecutableNotFoundException`. `StreamerException` for a missing or unwritten manifest; `InvalidMediaException` without streams.
- `config()` returns `['input' => ..., 'pipeline' => ...]` and `command()` the command line, without running anything.

## Configuration

`php artisan vendor:publish --tag=streamer-config`: `binary`, `system_binaries`, `packager`, `timeout`, and the defaults `video_codecs`, `audio_codecs`, `segment_duration`, `hardware_acceleration`, `ffmpeg_input_args`. `php artisan media:info` lists `shaka-streamer`.

## Testing

Never run the real binary in tests:

```php
use Foxws\Media\Facades\Media;
use Foxws\Streamer\StreamerExecutable;
use Foxws\Streamer\Testing\FakeStreamer;

Storage::fake('media');
$fake = FakeStreamer::respond(Media::fake());   // writes the manifests and video_{resolution}.mp4 placeholders

// ... run the code under test

$fake->assertRan(StreamerExecutable::ShakaStreamer);
$fake->assertSaved('1/master.m3u8', 'media');
$fake->failNext(StreamerExecutable::ShakaStreamer, 'RuntimeError: ffmpeg failed');
```

---
section: Reference
order: 2
---

# Upgrading from 2.x

3.0 is built on [foxws/laravel-media](https://github.com/foxws/laravel-media). The package now only adds Shaka Streamer: opening media, disks, temporary files, running processes, uploads, encryption keys, signed playlists, events and fakes come from laravel-media.

## Requirements

- Laravel 13 (12 is no longer supported), PHP 8.4.
- `foxws/laravel-media` ^0.3.4 is installed with the package.

## Encoding

The `Streamer` facade, `MediaOpener`, `Streamer`, `MediaExporter` and `CommandBuilder` are gone. Open media with laravel-media and call `streamer()`, then pass the directory to `save()`:

```php
// 2.x
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

// 3.0
Media::fromDisk('media')
    ->open('videos/clip.mp4')
    ->streamer()
    ->addVideoStream()
    ->addAudioStream()
    ->withResolutions('1080p', '720p', '480p')
    ->withDashManifest('index.mpd')
    ->withHlsPlaylist('master.m3u8')
    ->toDisk('s3')
    ->save('streams/clip');
```

- `cleanupTemporaryFiles()` is no longer needed: laravel-media deletes temporary files after every queue job and request.
- `export()` is gone: `toDisk()`, `withVisibility()`, `afterSaving()` and `save()` are on the builder. `save()` returns an `ExportResult` (the disk and the saved paths, manifests first) instead of the opener.
- `add*Stream()` no longer takes an output label: Shaka Streamer names the files itself. The path is optional and defaults to the first opened file; a path you didn't open is read from the same disk. The language is an argument of `addAudioStream()` and `addTextStream()`, and other input fields go in `options`.
- `addStream()`, the `Stream` value object and `VideoResolution` are removed. Shaka Streamer skips resolutions above the source, so you can pass every resolution you want at most.
- Without a manifest, `save()` throws instead of writing both.

## Methods

| 2.x | 3.0 |
| --- | --- |
| `withMpdOutput()`, `withHlsMasterPlaylist()` | `withDashManifest()`, `withHlsPlaylist()` |
| `withManifestFormat()` | removed: the formats follow from the manifests |
| `withResolutions([...])`, `withVideoCodecs([...])`, `withAudioCodecs([...])`, `withChannelLayouts(...)` | the same names, with one argument per value: `withResolutions('1080p', '720p')` |
| `withSegmentDuration()`, `withSegmentPerFile()` | `segmentDuration()`, `segmentPerFile()` |
| `withGenerateIframePlaylist()` | `withTrickPlay()` |
| `withLowLatencyDashMode()` | `lowLatencyDashMode()` |
| `withHwaccelApi()` | `withHardwareAcceleration()` |
| `withExtraInputArgs()` | `withFFmpegInputArgs()` |
| `withStreamingMode()`, `withLimitResolutionBy()`, `withSegmentFolder()` | `withOption('streaming_mode', ...)`, `withOption('limit_resolution_by', ...)`, `withOption('segment_folder', ...)` |
| `withAESEncryption($keyFile, $scheme, $label)` | `withEncryption(keyFile: $keyFile, scheme: ProtectionScheme::Cbcs, label: $label)` |
| `withEncryption([...])` | `withOption('encryption', [...])` for Shaka Streamer's own encryption config |
| `withKeyRotationDuration()` | removed (it always threw) |
| `useSystemBinaries()`, `withOption()` | unchanged; system binaries can also be turned on in the config |
| `getCommand()` | `command()`, and `config()` for the configs |

`withAESEncryption()` returned the key; `withEncryption()` generates one unless you pass an `EncryptionKey`, and `$result->encryptionKey()` returns it after `save()`. The key file is saved next to the segments, as before.

New: `addStreamsFrom()` adds every stream of the opened files, and `withLadder()` takes the resolutions and codec of a laravel-media ladder.

## Serving streams

`Streamer::dynamicHLSPlaylist()` and `Streamer::dynamicDASHManifest()` are replaced by laravel-media's:

```php
// 2.x
Streamer::dynamicHLSPlaylist('s3')->setMediaUrlResolver($resolver)->open('streams/clip/master.m3u8')->toResponse($request);

// 3.0
Media::fromDisk('s3')->open('streams/clip/master.m3u8')->hlsPlaylist()->resolveMediaUrlsUsing($resolver)->toResponse($request);
```

`setKeyUrlResolver()`, `setMediaUrlResolver()` and `setPlaylistUrlResolver()` are now `resolveKeyUrlsUsing()`, `resolveMediaUrlsUsing()` and `resolvePlaylistUrlsUsing()`. Resolvers receive the file's path on the disk.

## Events and exceptions

- `StreamingStarted`, `StreamingCompleted` and `StreamingFailed` are removed. Listen to laravel-media's `ProcessStarted`, `ProcessCompleted` and `ProcessFailed`, or `ExportCompleted` and `ExportFailed` (with `withContext()`).
- Shaka Streamer failures throw laravel-media's `ProcessFailedException`, with `isRetryable()`, instead of `RuntimeException`. A missing binary throws laravel-media's `ExecutableNotFoundException`.
- A missing manifest throws `Foxws\Streamer\Exceptions\StreamerException`, and invalid values throw `InvalidArgumentException`.

## Configuration

| 2.x | 3.0 |
| --- | --- |
| `streamer.streamer_binary` (`STREAMER_BINARY`) | `binary` (`STREAMER_BINARY`) |
| `hwaccel_api` (`STREAMER_HWACCEL_API`) | `hardware_acceleration` (`STREAMER_HARDWARE_ACCELERATION`) |
| `extra_input_args` (`STREAMER_EXTRA_INPUT_ARGS`) | `ffmpeg_input_args` (`STREAMER_FFMPEG_INPUT_ARGS`) |
| `timeout`, `video_codecs`, `audio_codecs`, `segment_duration` | unchanged |
| `streamer_options` | removed: use `withOptions()` |
| `log_channel`, `temporary_files_*`, `cache_files_*`, `concurrency_workers`, `multipart_*` | laravel-media's `config/media.php` |
| `force_generic_input` | removed: commands no longer run through a shell |

New: `system_binaries` (`STREAMER_SYSTEM_BINARIES`) and `packager` (`STREAMER_PACKAGER_BINARY`).

`php artisan streamer:info` is replaced by `php artisan media:info`, which lists `shaka-streamer`.

## Tests

Replace `Process::fake()` with `FakeStreamer::respond(Media::fake())`. See [Testing](testing.md).

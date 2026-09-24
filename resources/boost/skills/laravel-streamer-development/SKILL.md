---
name: laravel-streamer-development
description: Transcode and package video into DASH and HLS with foxws/laravel-streamer (Shaka Streamer), including resolution ladders, codecs, hardware acceleration, AES encryption, exporting to local or S3 disks, and serving manifests with signed URLs through DynamicHLSPlaylist and DynamicDASHManifest. Use when working with the Streamer facade, Foxws\Streamer classes, config/streamer.php, or building adaptive bitrate playlists.
---

# Streaming with laravel-streamer

`foxws/laravel-streamer` wraps [Shaka Streamer](https://shaka-project.github.io/shaka-streamer/), which runs FFmpeg to **transcode** into a resolution ladder and then Shaka Packager to segment it. If the media is already encoded and only needs packaging, `foxws/laravel-shaka` is much faster.

## Streaming flow

```php
use Foxws\Streamer\Facades\Streamer;
use Foxws\Streamer\Support\VideoResolution;

$streamer = Streamer::fromDisk('media')->open(['videos/clip.mp4']);

$streamer
    ->useSystemBinaries()
    ->addVideoStream('videos/clip.mp4', 'video.mp4')
    ->addAudioStream('videos/clip.mp4', 'audio.mp4')
    ->withResolutions([VideoResolution::make($height)->last()])
    ->withMpdOutput('index.mpd')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withStreamingMode('vod')
    ->withSegmentPerFile();

try {
    $streamer
        ->export()
        ->toDisk('segments')
        ->toPath("{$playlist->getKey()}/")
        ->afterSaving(fn ($exporter, $result) => $playlist->markAsReady())
        ->save();
} finally {
    $streamer->cleanupTemporaryFiles();
}
```

- The first argument of `add*Stream()` is the path you passed to `open()`. Shaka Streamer names output files itself, so the second argument is only a label.
- `useSystemBinaries()` uses the `ffmpeg` and `packager` on `PATH` instead of the ones bundled with Shaka Streamer. It only affects this streamer instance, so call it for every job that needs it.
- Resolutions: `VideoResolution::make($height)` gives the standard tiers (`144p` … `4k`, `8k`) at or below the source height. Pass the names to `withResolutions()`; don't upscale.
- Always call `cleanupTemporaryFiles()` in `finally`. Transcoding output is large and workers are long-lived.
- `save()` copies the output to the target disk and then deletes the temporary directory. S3 disks upload concurrently, and large files use multipart uploads. Local disks get a `rename()`.
- Captions: use `addTextStream($path, 'caption.mp4', ['language' => 'en'])` and output fragmented MP4 rather than a bare `.vtt`.

## Encryption

```php
$key = $streamer->withAESEncryption('key', 'cbcs');

// The package doesn't set hls_key_uri yet; set it so HLS playlists point at the uploaded key file.
$streamer->withEncryption([
    ...$streamer->getBuilder()->getOptions()->get('encryption'),
    'hls_key_uri' => 'key',
]);

// Store $key->keyId and $key->key (hex) to serve the key later.
```

- Shaka Streamer only accepts `cenc` (its default) and `cbcs`. Use `cbcs` to cover Safari and other browsers with one set of segments.
- Don't call `withKeyRotationDuration()`: Shaka Streamer has no rotation field, and the job fails with `Invalid Shaka Streamer configuration`.
- Leave `extra_input_args` empty for the same reason.
- The key file is written to `cache_files_root` and uploaded next to the segments. Serve it only through an authorized route or a short-lived signed URL. DASH players need the key themselves, such as Shaka Player's `drm.clearKeys`.

## Serving manifests with signed URLs

```php
return Streamer::dynamicHLSPlaylist()
    ->setKeyUrlResolver(fn (string $path) => Storage::disk('segments')->temporaryUrl("{$id}/{$path}", now()->addMinutes(10)))
    ->setMediaUrlResolver(fn (string $path) => Storage::disk('segments')->temporaryUrl("{$id}/{$path}", now()->addHour()))
    ->setPlaylistUrlResolver(fn (string $path) => URL::temporarySignedRoute('manifest', now()->addHour(), [$id, $path]))
    ->fromDisk('segments')
    ->open("{$id}/master.m3u8")
    ->toResponse($request);
```

`Streamer::dynamicDASHManifest()` works the same with `setInitUrlResolver()` and `setMediaUrlResolver()`.

## Configuration

Publish with `php artisan vendor:publish --tag=streamer-config`. Check the install with `php artisan streamer:info`.

| Key | Purpose |
| --- | --- |
| `streamer.streamer_binary` | Path to `shaka-streamer` (`pip install shaka-streamer`) |
| `video_codecs`, `audio_codecs` | Default codecs, e.g. `h264`, `av1`, `aac`, `opus` |
| `hwaccel_api` | Hardware encoding, e.g. `vaapi`, `nvenc` |
| `segment_duration`, `streamer_options` | Pipeline defaults (`extra_input_args` must stay empty) |
| `temporary_files_root` | Where output is written before upload; needs room for every rendition |
| `cache_files_root` | Small files such as keys (default `/dev/shm`) |
| `temporary_files_min_free`, `cache_files_min_free` | Fixed free-space floors; throw `InsufficientStorageException` when a root is too full |
| `concurrency_workers` | Parallel S3 uploads |
| `multipart_threshold`, `multipart_part_size`, `multipart_concurrency` | Multipart upload tuning for large files |
| `timeout` | Process timeout; keep it at or below the queue job's `$timeout` |

## Events

`StreamingStarted`, `StreamingCompleted` (`$result`, `$executionTime`) and `StreamingFailed` are dispatched around each run.

## Testing

Don't run Shaka Streamer in unit tests. Assert on the generated config with `->getCommand()`, and fake the target with `Storage::fake()`.

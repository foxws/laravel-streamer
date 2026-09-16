---
section: Usage
order: 1
---

# Usage

## Basic packaging

```php
use Foxws\Streamer\Facades\Streamer;

Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->save();
```

## Dual DASH + HLS output (CMAF)

By default, Shaka Streamer packages video and audio as CMAF (fragmented MP4).
Because both DASH and HLS can describe the same CMAF segments, you can produce
both manifests from a single run. Set `withMpdOutput()` and
`withHlsMasterPlaylist()` together and you get both — no extra transcoding,
just one more manifest file:

```php
Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->save();
```

You don't need to call `withManifestFormat(['dash', 'hls'])` yourself — it's
worked out automatically from whichever outputs you set. Call it explicitly
only if you want to be specific about which manifest(s) get generated.

## Cross-disk workflows

You can read the source file from one disk and write the packaged output to
another:

```php
Streamer::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video.mp4')
    ->addAudioStream('videos/input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->toDisk('export')
    ->toPath('streams/')
    ->withVisibility('public')
    ->save();
```

## Encryption

`withAESEncryption()` doesn't return `$this` — it returns an `EncryptionKey`
object instead. That means it breaks the fluent chain, so call it on its own
line rather than in the middle of a chain:

```php
// AES-128 encryption with auto-generated key
$streamer = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8');

$encryptionKey = $streamer->withAESEncryption();

$streamer->export()->save();

// With key rotation (rotates every 60 seconds)
$streamer = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8');

$encryptionKey = $streamer->withAESEncryption('key', 'cenc');
$streamer->withKeyRotationDuration(60);

$streamer->export()->toDisk('s3')->save();
```

See the [AES Encryption Guide](./aes-encryption.md) for protection schemes, codec-specific examples, and key management.

## Dynamic URL resolvers

You can serve HLS and DASH content with signed URLs — handy for S3, CDNs, or apps with multiple tenants.

**HLS:**

```php
use Foxws\Streamer\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

return (new DynamicHLSPlaylist('s3'))
    ->open("videos/{$video->id}/master.m3u8")
    ->setKeyUrlResolver(fn (string $key) => Storage::disk('s3')->temporaryUrl(
        "videos/{$video->id}/{$key}",
        now()->addHour(),
    ))
    ->setMediaUrlResolver(fn (string $file) => Storage::disk('s3')->temporaryUrl(
        "videos/{$video->id}/{$file}",
        now()->addHours(2),
    ))
    ->setPlaylistUrlResolver(fn (string $playlist) => route('video.playlist', [
        'video' => $video,
        'playlist' => $playlist,
    ]))
    ->toResponse(request());
```

**DASH:**

```php
use Foxws\Streamer\Http\DynamicDASHManifest;
use Illuminate\Support\Facades\Storage;

return (new DynamicDASHManifest('s3'))
    ->open("videos/{$video->id}/manifest.mpd")
    ->setMediaUrlResolver(fn (string $file) => Storage::disk('s3')->temporaryUrl(
        "videos/{$video->id}/{$file}",
        now()->addHours(2),
    ))
    ->setInitUrlResolver(fn (string $file) => Storage::disk('s3')->temporaryUrl(
        "videos/{$video->id}/{$file}",
        now()->addHours(2),
    ))
    ->toResponse(request());
```

See [URL Resolvers](./url-resolvers.md) for more details.

## Events

You can listen for these streaming lifecycle events:

| Event                | Payload                                              |
| --------------------- | ---------------------------------------------------- |
| `StreamingStarted`   | `MediaCollection $mediaCollection`, `array $options` |
| `StreamingCompleted` | `StreamerResult $result`, `float $executionTime`     |
| `StreamingFailed`    | `Exception $exception`, `float $executionTime`       |

## Post-export inspection

After saving, you can inspect what happened:

```php
$exporter = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export();

$exporter->afterSaving(function ($exporter, $result) {
    $summary = $exporter->getCopySummary();
    // ['total' => 12, 'copied' => 12, 'failed' => 0, 'totalSize' => 8421376]

    $keys = $result->getUploadedEncryptionKeys();
});

$exporter->toDisk('s3')->save();
```

## API reference

### `Streamer` facade → `MediaOpener`

| Method                               | Description                                      |
| ------------------------------------ | ------------------------------------------------ |
| `fromDisk($disk)`                    | Set the source filesystem disk                   |
| `open($paths)`                       | Open one or more media files                     |
| `openFromDisk($disk, $paths)`        | Set disk and open files in one call              |
| `get()`                              | Get the `MediaCollection`                        |
| `export()`                           | Start the export chain (returns `MediaExporter`) |
| `dynamicHLSPlaylist(?string $disk)`  | Create a `DynamicHLSPlaylist` instance           |
| `dynamicDASHManifest(?string $disk)` | Create a `DynamicDASHManifest` instance          |
| `cleanupTemporaryFiles()`            | Delete all temporary directories                 |

### Stream configuration (via `MediaOpener` → `Streamer`)

| Method                                             | Description                                                                        |
| --------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `addVideoStream($input, $output, $options)`        | Add a video stream                                                                 |
| `addAudioStream($input, $output, $options)`        | Add an audio stream                                                                |
| `addTextStream($input, $output, $options)`         | Add a text/subtitle stream                                                        |
| `addStream(Stream\|array $stream)`                 | Add a `Stream` value object or raw stream descriptor (`in`, `stream`, `output`, …) |
| `withHlsMasterPlaylist($path)`                     | Set HLS output                                                                     |
| `withMpdOutput($path)`                             | Set DASH/MPD output                                                               |
| `withSegmentDuration(int $seconds)`                | Set segment duration                                                              |
| `withManifestFormat(array $formats)`               | Set manifest formats (e.g. `['dash', 'hls']`)                                     |
| `withResolutions(array $resolutions)`              | Set encoding resolutions                                                          |
| `withVideoCodecs(array $codecs)`                   | Set video codecs (e.g. `['h264', 'hw:vp9']`)                                      |
| `withAudioCodecs(array $codecs)`                   | Set audio codecs (e.g. `['aac', 'opus']`)                                         |
| `withSegmentPerFile(bool $enabled)`                | Enable segment-per-file output                                                    |
| `withLowLatencyDashMode(bool $enabled)`            | Enable low-latency DASH                                                           |
| `withStreamingMode(string $mode)`                  | Set mode (`'vod'` or `'live'`)                                                    |
| `withEncryption(array $config)`                    | Set raw encryption config                                                         |
| `withAESEncryption($keyFilename, $scheme, $label)` | Auto-generate AES encryption key, returns `EncryptionKey` (not `$this`)           |
| `withKeyRotationDuration(int $seconds)`            | Enable key rotation (requires `'cenc'` or `'cbcs'`)                               |
| `withOption($key, $value)`                         | Set a custom pipeline option                                                      |
| `withOptions(array $options)`                      | Set multiple custom pipeline options                                              |
| `getCommand()`                                     | Get the built config array (for debugging)                                       |

### `MediaExporter` (returned by `export()`)

| Method                               | Description                                   |
| ------------------------------------- | ---------------------------------------------- |
| `toDisk($disk)`                      | Set target disk for output                    |
| `toPath(string $path)`               | Set target subdirectory                       |
| `withVisibility(string $visibility)` | Set file visibility (`'public'`, `'private'`) |
| `afterSaving(callable $callback)`    | Register post-save callback                   |
| `save(?string $path)`                | Execute packaging and copy files to disk      |
| `getCommand()`                       | Get the built config array                    |
| `dd()`                               | Dump config and die                           |
| `getCopySummary()`                   | Get `{total, copied, failed, totalSize}`      |
| `getCopiedFiles()`                   | Get array of successfully copied files        |
| `getFailedFiles()`                   | Get array of failed file copies               |
| `hasCopyFailures()`                  | Check if any files failed to copy             |

### `DynamicHLSPlaylist`

| Method                             | Description                                        |
| ------------------------------------ | --------------------------------------------------- |
| `open(string $path)`               | Open a playlist file                               |
| `setKeyUrlResolver(callable)`      | Resolve encryption key URLs                        |
| `setMediaUrlResolver(callable)`    | Resolve media segment URLs                         |
| `setPlaylistUrlResolver(callable)` | Resolve sub-playlist URLs                          |
| `get()`                            | Get processed playlist content                     |
| `all()`                            | Get all processed playlists (master + variants)    |
| `toResponse($request)`             | Return as `application/vnd.apple.mpegurl` response |

### `DynamicDASHManifest`

| Method                          | Description                               |
| --------------------------------- | ------------------------------------------ |
| `open(string $path)`            | Open a manifest file                      |
| `setMediaUrlResolver(callable)` | Resolve media segment URLs                |
| `setInitUrlResolver(callable)`  | Resolve initialization segment URLs       |
| `get()`                         | Get processed manifest content            |
| `toResponse($request)`          | Return as `application/dash+xml` response |

## Configuration

Some of the key options in `config/streamer.php`:

| Option                     | Default                             | Description                                    |
| ---------------------------- | -------------------------------------- | ------------------------------------------------- |
| `streamer.streamer_binary` | `'shaka-streamer'`                  | Path to the Shaka Streamer binary              |
| `timeout`                  | `14400` (4h)                        | Process timeout in seconds                     |
| `temporary_files_root`     | `storage_path('app/streamer/temp')` | Directory for temporary files                  |
| `cache_files_root`         | `'/dev/shm'`                        | Fast storage for small files (keys, manifests) |
| `log_channel`              | `null`                              | Log channel for streamer output                |

See [Configuration](./configuration.md) for all options and environment variables.

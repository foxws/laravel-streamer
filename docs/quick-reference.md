---
section: Reference
order: 1
---

# Quick Reference

## Fluent API with fromDisk support

### Basic usage

```php
use Foxws\Streamer\Facades\Streamer;

// Default disk
$result = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->export();
```

### Using different disks

```php
// From S3, save to a different disk (e.g., local, s3, etc.)
$result = Streamer::fromDisk('s3')
    ->open('videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video.mp4')
    ->withMpdOutput('manifest.mpd')
    ->export()
    ->toDisk('export')
    ->save();

// Helper method
$result = Streamer::openFromDisk('s3', 'videos/input.mp4')
    ->addVideoStream('videos/input.mp4', 'video.mp4')
    ->export()
    ->toDisk('export')
    ->save();
```

## Available methods

### Disk management

| Method | Description |
| --- | --- |
| `fromDisk(string $disk)` | Set the disk to use |
| `openFromDisk(string $disk, $paths)` | Set disk and open files in one call |
| `getDisk()` | Get current disk instance |

### Media management

| Method | Description |
| --- | --- |
| `open($paths)` | Open one or more media files |
| `get()` | Get the MediaCollection |
| `streams()` | Get auto-generated Stream objects |

### Stream configuration

| Method | Description |
| --- | --- |
| `addVideoStream(string $input, string $output, array $options = [])` | Add a video stream |
| `addAudioStream(string $input, string $output, array $options = [])` | Add an audio stream |
| `addTextStream(string $input, string $output, array $options = [])` | Add a text/caption/subtitle stream |
| `addStream(array $stream)` | Add a custom stream with full control |

### Output configuration

| Method | Description |
| --- | --- |
| `withMpdOutput(string $path)` | Set DASH manifest output |
| `withHlsMasterPlaylist(string $path)` | Set HLS master playlist output |
| `withSegmentDuration(int $seconds)` | Set segment duration |
| `withEncryption(array $config)` | Enable encryption |
| `toDisk(string $disk)` | Set the target disk for output |
| `toPath(string $path)` | Set the target output path (subdirectory) |
| `withVisibility(string $visibility)` | Set file visibility (e.g. `'public'`, `'private'`) |

### Execution & utilities

| Method | Description |
| --- | --- |
| `export()` | Export the packaging operation (returns a result object) |
| `save(?string $path = null)` | Save outputs to disk (optionally to a specific path) |
| `getCommand()` | Get the final command string (for debugging) |
| `dd()` | Dump the final command and stop the script |
| `afterSaving(callable $callback)` | Register a callback to run after saving |

### Dynamic URL resolvers

**DynamicHLSPlaylist:**

| Method | Description |
| --- | --- |
| `new DynamicHLSPlaylist(?string $disk)` | Create an HLS playlist processor |
| `open(string $path)` | Open a playlist file |
| `setKeyUrlResolver(callable $resolver)` | Set the resolver for encryption key URLs |
| `setMediaUrlResolver(callable $resolver)` | Set the resolver for media segment URLs |
| `setPlaylistUrlResolver(callable $resolver)` | Set the resolver for sub-playlist URLs |
| `get()` | Get processed playlist content |
| `all()` | Get all processed playlists (master + segments) |
| `toResponse($request)` | Return as an HTTP response |

**DynamicDASHManifest:**

| Method | Description |
| --- | --- |
| `new DynamicDASHManifest(?string $disk)` | Create a DASH manifest processor |
| `open(string $path)` | Open a manifest file |
| `setMediaUrlResolver(callable $resolver)` | Set the resolver for media segment URLs |
| `setInitUrlResolver(callable $resolver)` | Set the resolver for initialization segment URLs |
| `get()` | Get processed manifest content |
| `toResponse($request)` | Return as an HTTP response |

## Common patterns

### Adding captions/subtitles (WebVTT)

```php
Streamer::fromDisk('s3')
    ->open('videos/source.mp4')
    ->addVideoStream('videos/source.mp4', 'video_1080p.mp4', [
        'bandwidth' => '5000000',
    ])
    ->addAudioStream('videos/source.mp4', 'audio.mp4')
    ->addTextStream('captions/english.vtt', 'english.vtt', [
        'language' => 'en',
    ])
    ->withMpdOutput('manifest.mpd')
    ->withSegmentDuration(6)
    ->export();
```

### Adaptive bitrate streaming

```php
Streamer::fromDisk('s3')
    ->open('videos/source.mp4')
    ->addVideoStream('videos/source.mp4', 'video_1080p.mp4', [
        'bandwidth' => '5000000',
    ])
    ->addVideoStream('videos/source.mp4', 'video_720p.mp4', [
        'bandwidth' => '3000000',
    ])
    ->addVideoStream('videos/source.mp4', 'video_480p.mp4', [
        'bandwidth' => '1500000',
    ])
    ->addAudioStream('videos/source.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->withSegmentDuration(6)
    ->export();
```

### HLS with encryption

```php
Streamer::fromDisk('s3')
    ->open('secure/video.mp4')
    ->addVideoStream('secure/video.mp4', 'video.m3u8')
    ->addAudioStream('secure/video.mp4', 'audio.m3u8')
    ->withHlsMasterPlaylist('master.m3u8')
    ->withEncryption([
        'keys' => 'label=:key_id=abc:key=def',
        'key_server_url' => 'https://example.com/license',
    ])
    ->export();
```

### Multiple files

```php
Streamer::fromDisk('videos')
    ->open(['intro.mp4', 'main.mp4', 'outro.mp4'])
    ->addVideoStream('intro.mp4', 'intro_video.mp4')
    ->addVideoStream('main.mp4', 'main_video.mp4')
    ->addVideoStream('outro.mp4', 'outro_video.mp4')
    ->withMpdOutput('manifest.mpd')
    ->export();
```

### Error handling

```php
try {
    $result = Streamer::fromDisk('s3')
        ->open('video.mp4')
        ->addVideoStream('video.mp4', 'output.mp4')
        ->withMpdOutput('manifest.mpd')
        ->export();

    logger()->info('Success', $result->getOutput());
} catch (\Foxws\Streamer\Exceptions\RuntimeException $e) {
    logger()->error('Packaging failed', ['error' => $e->getMessage()]);
} catch (\InvalidArgumentException $e) {
    logger()->error('Invalid input', ['error' => $e->getMessage()]);
}
```

## Configuration

See the [Configuration](./configuration.md) page for the full list of options and environment variables.

## Artisan commands

```bash
# Show binary and configuration status
php artisan streamer:info
```

## Direct driver usage

```php
use Foxws\Streamer\Support\ShakaStreamer;

$driver = ShakaStreamer::create();
$version = $driver->getVersion();
$driver->setTimeout(7200);
```

## CommandBuilder direct usage

```php
use Foxws\Streamer\Support\CommandBuilder;
use Foxws\Streamer\Support\Streamer;

$builder = CommandBuilder::make()
    ->addVideoStream('input.mp4', 'output.mp4')
    ->withMpdOutput('manifest.mpd');

$streamer = app(Streamer::class);
$result = $streamer->streamWithBuilder($builder);
```

## Stream objects

```php
use Foxws\Streamer\Support\Stream;
use Foxws\Streamer\Filesystem\Media;

$media = Media::make('videos', 'input.mp4');

$videoStream = Stream::video($media)
    ->setOutput('video.mp4')
    ->addOption('bandwidth', '5000000');

$audioStream = Stream::audio($media)
    ->setOutput('audio.mp4');

$commandString = $videoStream->toCommandString();
```

## Examples location

- Basic examples: `examples/StreamerExamples.php`
- Fluent API examples: `examples/FluentBuilderExamples.php`
- fromDisk examples: `examples/FromDiskExamples.php`

## Testing

```php
// Unit tests
vendor/bin/pest tests/Unit/ShakaStreamerDriverTest.php
vendor/bin/pest tests/Unit/StreamerTest.php
vendor/bin/pest tests/Unit/FromDiskTest.php
```

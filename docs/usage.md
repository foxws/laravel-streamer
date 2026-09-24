---
section: Usage
order: 1
---

# Usage

## The basic flow

Every job follows the same steps: open the input, add streams, choose qualities and manifests, then export and save.

```php
use Foxws\Streamer\Facades\Streamer;

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
```

- `fromDisk()` picks the disk to read from. Without it, your default filesystem disk is used.
- The first argument of `addVideoStream()` and friends is a path you passed to `open()`. Shaka Streamer names the output files itself, so the second argument is only a label.
- `export()` returns the exporter. Nothing runs until you call `save()`.
- `save()` runs Shaka Streamer, copies the output to the target disk and deletes the local copy.
- `cleanupTemporaryFiles()` removes anything left behind, such as downloaded inputs or the output of a failed run. Always call it in `finally`, especially in queue workers.

## Where the output goes

Shaka Streamer writes to a local temporary directory first. `save()` then copies everything to the target disk:

- `toDisk()` sets the target disk. Without it, the input disk is used.
- `toPath()` sets the folder on that disk. Without it, files land in the root of the disk.
- `withVisibility('private')` sets the visibility of the uploaded files.

S3 disks upload in parallel, and large files use multipart uploads. On a local disk, files are moved instead of copied. See [Configuration](configuration.md).

## Qualities

`withResolutions()` sets the qualities to encode. Shaka Streamer knows `144p`, `240p`, `360p`, `480p`, `576p`, `720p`, `1080p`, `1440p`, `4k` and `8k`.

Don't encode above the source. `VideoResolution` gives you the standard qualities up to a given height:

```php
use Foxws\Streamer\Support\VideoResolution;

$resolutions = VideoResolution::make($height)->toArray(); // 720 gives ['144p', '240p', '360p', '480p', '576p', '720p']
$highest = VideoResolution::make($height)->last();        // '720p'
```

Every extra quality adds encoding time. Three or four is enough for most videos.

## Codecs

The defaults come from the config (`h264` video, `aac` audio). Change them per job:

```php
->withVideoCodecs(['h264', 'av1'])
->withAudioCodecs(['aac', 'opus'])
```

Each codec is encoded separately, so two codecs roughly double the work. Prefix a video codec with `hw:` to use hardware encoding, such as `hw:h264`. This needs `hwaccel_api` in the config and an FFmpeg build that supports it.

## DASH and HLS together

Streams are written as fragmented MP4 (CMAF), so one set of segments works for both DASH and HLS. Set both outputs and you get both manifests from a single run:

```php
->withMpdOutput('index.mpd')
->withHlsMasterPlaylist('master.m3u8')
```

The manifest formats follow from the outputs you set. `withManifestFormat()` is only needed to override that.

## Other options

```php
->withSegmentDuration(6)       // segment length in seconds
->withSegmentPerFile()         // one file per segment, instead of one file per stream
->withStreamingMode('vod')     // 'vod' (default) or 'live'
->withGenerateIframePlaylist() // HLS trick-play playlists
->withOption('scene_detection', false)
```

`withOption()` sets any other field of Shaka Streamer's [pipeline config](https://shaka-project.github.io/shaka-streamer/configuration_fields.html).

## Subtitles

Open the subtitle file along with the video, then add it as a text stream:

```php
Streamer::fromDisk('media')
    ->open(['videos/clip.mp4', 'captions/clip.en.vtt'])
    ->addVideoStream('videos/clip.mp4', 'video.mp4')
    ->addAudioStream('videos/clip.mp4', 'audio.mp4')
    ->addTextStream('captions/clip.en.vtt', 'subtitles-en.mp4', ['language' => 'en'])
    ->withMpdOutput('index.mpd')
    ->withHlsMasterPlaylist('master.m3u8')
    ->export()
    ->save();
```

A path that you didn't open is passed to Shaka Streamer as-is, so it must be an absolute local path.

## System binaries

By default, Shaka Streamer uses the FFmpeg and Shaka Packager from `shaka-streamer-binaries`. To use the ones on your `PATH` instead:

```php
$streamer = Streamer::fromDisk('media')->open('videos/clip.mp4')->useSystemBinaries();
```

This only applies to this job. Call it on every job that needs it.

## After saving

`afterSaving()` runs after the files are on the target disk:

```php
->export()
->toDisk('s3')
->afterSaving(fn ($exporter, $result) => $video->markAsReady())
->save();
```

## Errors

- A `RuntimeException` from Shaka Streamer means the encode or package step failed. The message includes its error output.
- A `RuntimeException` from `save()` that lists files means some files couldn't be copied to the target disk.
- `Foxws\Streamer\Exceptions\InsufficientStorageException` means a storage guard stopped the job before it started. See [Configuration](configuration.md).

## Events

| Event | Properties |
| --- | --- |
| `Foxws\Streamer\Events\StreamingStarted` | `$mediaCollection`, `$options` |
| `Foxws\Streamer\Events\StreamingCompleted` | `$result`, `$executionTime` |
| `Foxws\Streamer\Events\StreamingFailed` | `$exception`, `$executionTime`, `$context` |

## Debugging

`getCommand()` returns the input and pipeline config that would be sent to Shaka Streamer, without running it:

```php
$config = Streamer::open('videos/clip.mp4')
    ->addVideoStream('videos/clip.mp4', 'video.mp4')
    ->withMpdOutput('index.mpd')
    ->getCommand();
```

Or call `->export()->dd()` to dump it and stop.

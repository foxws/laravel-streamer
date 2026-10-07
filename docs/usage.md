---
section: Usage
order: 1
---

# Usage

## The basic flow

Open the input, add streams, choose the qualities and manifests, then save to a directory on the target disk:

```php
use Foxws\Media\Facades\Media;

$result = Media::fromDisk('media')
    ->open('videos/clip.mp4')
    ->streamer()
    ->addVideoStream()
    ->addAudioStream(language: 'en')
    ->withResolutions('1080p', '720p', '480p')
    ->withHlsPlaylist('master.m3u8')
    ->withDashManifest('manifest.mpd')
    ->toDisk('s3')
    ->save('streams/clip');

$result->paths(); // ['streams/clip/master.m3u8', 'streams/clip/manifest.mpd', ...]
```

- `add*Stream()` reads the first opened file unless you pass a path. A path you didn't open is read from the same disk.
- `addStreamsFrom()` probes every opened file and adds its video and audio streams.
- Shaka Streamer names the segment files itself. Only the manifest paths are yours to choose.
- The output defaults to the disk the media was opened from. `withVisibility('private')` sets the visibility of the uploaded files.
- There's nothing to clean up: laravel-media deletes temporary files after every queue job and request.

## Qualities

`withResolutions()` sets the qualities to encode. Shaka Streamer knows `144p`, `240p`, `360p`, `480p`, `576p`, `720p`, `1080p`, `1440p`, `4k` and `8k` (and `-hfr` variants for high frame rates). It skips resolutions above the source.

`withLadder()` takes the resolutions and video codec of a laravel-media ladder, for example to share one ladder definition across your app. Shaka Streamer chooses the bitrates:

```php
use Foxws\Media\Encoding\Ladder;

->withLadder(Ladder::standard()) // 1080p, 720p, 480p and 360p in H.264
```

Every extra quality adds encoding time. Three or four is enough for most videos.

## Codecs

The defaults come from the config (`h264` video, `aac` audio). Change them per run:

```php
->withVideoCodecs('h264', 'av1')
->withAudioCodecs('aac', 'opus')
->withChannelLayouts('stereo', 'surround')
```

Each codec is encoded separately, so two codecs roughly double the work. Prefix a video codec with `hw:` to use hardware encoding, and set the API:

```php
->withVideoCodecs('hw:h264')
->withHardwareAcceleration('vaapi')
```

This needs an FFmpeg build that supports it, usually with [system binaries](installation.md#install-shaka-streamer).

## Other options

```php
->segmentDuration(6)       // segment length in seconds
->segmentPerFile()         // one file per segment, instead of one file per stream
->withTrickPlay()          // HLS I-frame playlists for fast seeking
->lowLatencyDashMode()
->withFFmpegInputArgs('-hwaccel vaapi')
->withOption('streaming_mode', 'live')
```

`withOption()` sets any other field of Shaka Streamer's [pipeline config](https://shaka-project.github.io/shaka-streamer/configuration_fields.html), and `null` removes it. The `options` argument of `add*Stream()` sets input fields, such as `track_num`, `start_time` or `extra_input_args`.

## Subtitles

Add WebVTT files from the same disk as text streams:

```php
->addTextStream('captions/clip.en.vtt', 'en')
```

## Encryption

```php
use Foxws\Media\Encryption\ProtectionScheme;

$result = Media::fromDisk('media')->open('videos/clip.mp4')
    ->streamer()
    ->addStreamsFrom()
    ->withHlsPlaylist()
    ->withDashManifest()
    ->withEncryption(scheme: ProtectionScheme::Cbcs, clearLead: 2)
    ->save('streams/clip');

$result->encryptionKey(); // store it for your key or license route
```

`withEncryption()` generates a key unless you pass a laravel-media `EncryptionKey`. The raw key is saved next to the segments as `key`, and HLS playlists point to it; pass `keyFile: null` and `keyUri` to serve a stored key yourself. DASH manifests have no key URL, so give DASH players the key yourself (e.g. Shaka Player's `drm.clearKeys`).

Shaka Streamer encrypts with `cenc` (the default) or `cbcs`; `cbcs` plays with both HLS and DASH, including Safari. It has no key rotation: package with [laravel-shaka](https://github.com/foxws/laravel-shaka) when you need it.

## Serving the streams

Serve private streams with laravel-media's `DynamicHLSPlaylist` and `DynamicDASHManifest`, which sign every URL in the playlist when it's requested:

```php
return Media::fromDisk('s3')->open('streams/clip/master.m3u8')
    ->hlsPlaylist()
    ->resolveMediaUrlsUsing(fn (string $path) => Storage::disk('s3')->temporaryUrl($path, now()->addHour()))
    ->resolveKeyUrlsUsing(fn (string $key) => route('videos.key', $video))
    ->toResponse($request);
```

## Queues and errors

- Encode in a queued job. Give long runs `->timeout($seconds)` below the job's `$timeout` (default: `streamer.timeout`).
- A failed run throws laravel-media's `ProcessFailedException`, with Shaka Streamer's error output; use `isRetryable()` to choose between `release()` and `fail()`. A missing binary throws `ExecutableNotFoundException`.
- `Foxws\Streamer\Exceptions\StreamerException`: no manifest was chosen, or Shaka Streamer finished without writing one. Saving without streams throws laravel-media's `InvalidMediaException`.
- `save()` dispatches laravel-media's `ExportCompleted` and `ExportFailed`, with the data from `withContext()`. `beforeSaving()` and `afterSaving()` run around it.
- `config()` returns the input and pipeline configs, and `command()` the command line, without running it.

---
section: Reference
order: 1
---

# Quick Reference

## Opening media

Called on the `Streamer` facade.

| Method | Purpose |
| --- | --- |
| `fromDisk($disk)` | Disk to read input from. A disk name or a `Filesystem`. |
| `open($paths)` | Open one path, an array of paths, or an `UploadedFile`. |
| `openFromDisk($disk, $paths)` | `fromDisk()` and `open()` in one call. |
| `get()` | The opened `MediaCollection`. |
| `each($items, $callback)` | Run the callback with a fresh opener for each item. |
| `cleanupTemporaryFiles()` | Delete all temporary files. |
| `dynamicHLSPlaylist($disk)` | See [URL Resolvers](url-resolvers.md). |
| `dynamicDASHManifest($disk)` | See [URL Resolvers](url-resolvers.md). |

## Streams

| Method | Purpose |
| --- | --- |
| `addVideoStream($input, $output, $options = [])` | Video from an opened input. |
| `addAudioStream($input, $output, $options = [])` | Audio from an opened input. |
| `addTextStream($input, $output, $options = [])` | Subtitles from an opened input. |
| `addStream(Stream\|array $stream)` | A raw stream. Paths aren't resolved, so use absolute paths. |

`$options` are fields of Shaka Streamer's [input config](https://shaka-project.github.io/shaka-streamer/configuration_fields.html), such as `language` or `track_num`. Shaka Streamer names the output files itself, so `$output` is only a label.

## Encoding

| Method | Purpose |
| --- | --- |
| `withResolutions($resolutions)` | Qualities to encode, such as `['1080p', '720p']`. |
| `withVideoCodecs($codecs)` | Video codecs, such as `['h264', 'hw:av1']`. |
| `withAudioCodecs($codecs)` | Audio codecs, such as `['aac', 'opus']`. |
| `withHwaccelApi($api)` | `vaapi`, `nvenc` or `videotoolbox`. |
| `withLimitResolutionBy($dimension)` | Compare resolutions by `height` or `max_dimension`. |
| `withChannelLayouts($layouts)` | Audio channel layouts, as a list or a comma-separated string. |
| `useSystemBinaries()` | Use `ffmpeg` and `packager` from `PATH`. |

## Output

| Method | Purpose |
| --- | --- |
| `withMpdOutput($path)` | Write a DASH manifest. |
| `withHlsMasterPlaylist($path)` | Write an HLS master playlist. |
| `withManifestFormat($formats)` | Override the manifest formats, such as `['dash']`. |
| `withSegmentDuration($seconds)` | Segment length. |
| `withSegmentPerFile()` | One file per segment. |
| `withSegmentFolder($folder)` | Put segments in a subfolder. |
| `withStreamingMode($mode)` | `vod` (default) or `live`. |
| `withGenerateIframePlaylist()` | HLS trick-play playlists. |
| `withLowLatencyDashMode()` | Low-latency DASH. |
| `withOption($key, $value)` | Any other pipeline config field. |

## Encryption

| Method | Purpose |
| --- | --- |
| `withAESEncryption($keyFilename = 'key', $scheme = null, $label = null)` | Generate a key and encrypt. Returns an `EncryptionKey`. |
| `withEncryption($config)` | Set Shaka Streamer's encryption config directly. |

See [Encryption](aes-encryption.md). Shaka Streamer has no key rotation, so `withKeyRotationDuration()` throws.

## Exporting

Called on the result of `export()`.

| Method | Purpose |
| --- | --- |
| `toDisk($disk)` | Disk to write to. Defaults to the input disk. |
| `toPath($path)` | Folder on that disk. Defaults to the root. |
| `withVisibility($visibility)` | `public` or `private`. |
| `afterSaving($callback)` | Runs after upload, with `($exporter, $result)`. |
| `save($path = null)` | Run Shaka Streamer and upload the output. A path works like `toPath()`. |
| `getCommand()` | The config that would be sent, without running it. |
| `dd()` | Dump the config and stop. |

## Artisan

| Command | Purpose |
| --- | --- |
| `streamer:info` | Check the binary, version and temporary directory. |

## Classes

| Class | Purpose |
| --- | --- |
| `Foxws\Streamer\Facades\Streamer` | Entry point. |
| `Foxws\Streamer\Support\Streamer` | Holds the streams and runs a job. |
| `Foxws\Streamer\Support\CommandBuilder` | Builds the input and pipeline config. |
| `Foxws\Streamer\Support\ShakaStreamer` | Runs the binary. |
| `Foxws\Streamer\Support\StreamerResult` | Output of a run. Uploads it with `toDisk()`. |
| `Foxws\Streamer\Support\VideoResolution` | Standard qualities up to a height. |
| `Foxws\Streamer\Support\EncryptionKey` | A generated key: `key`, `keyId`, `filePath`. |
| `Foxws\Streamer\Http\DynamicHLSPlaylist` | Rewrites HLS playlists. |
| `Foxws\Streamer\Http\DynamicDASHManifest` | Rewrites DASH manifests. |

---
section: Reference
order: 2
---

# Configuration

Publish the config file to change the defaults:

```bash
php artisan vendor:publish --tag="streamer-config"
```

This creates `config/streamer.php`. Most options can also be set in `.env`.

## Binary and process

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `streamer.streamer_binary` | `STREAMER_BINARY` | `shaka-streamer` | Path to Shaka Streamer, or its name on `PATH`. |
| `timeout` | `STREAMER_TIMEOUT` | `14400` | Seconds before the process is stopped. See [Queues](queue-integration.md). |
| `log_channel` | `STREAMER_LOG_CHANNEL` | your `LOG_CHANNEL` | Channel for streamer logs. `false` turns logging off, `null` uses the default channel. Keys are redacted from logs. |

## Encoding defaults

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `video_codecs` | `STREAMER_VIDEO_CODECS` | `h264` | Comma-separated video codecs, such as `h264,av1` or `hw:h264`. |
| `audio_codecs` | `STREAMER_AUDIO_CODECS` | `aac` | Comma-separated audio codecs, such as `aac,opus`. |
| `segment_duration` | `STREAMER_SEGMENT_DURATION` | `6` | Segment length in seconds. |
| `hwaccel_api` | `STREAMER_HWACCEL_API` | `null` | Hardware encoding API for `hw:` codecs: `vaapi`, `nvenc` or `videotoolbox`. |
| `streamer_options` | | `[]` | Extra fields for Shaka Streamer's [pipeline config](https://shaka-project.github.io/shaka-streamer/configuration_fields.html), added to every job. |
| `force_generic_input` | `STREAMER_FORCE_GENERIC_INPUT` | `true` | Links each input as `input.<ext>` in a temporary folder, so special characters in names can't cause problems. |
| `extra_input_args` | `STREAMER_EXTRA_INPUT_ARGS` | `null` | Leave this empty. It's added to the pipeline config, but Shaka Streamer only accepts it per input, so any value makes jobs fail. |

A codec with the `hw:` prefix needs `hwaccel_api`, and an FFmpeg build with that encoder. Use your own FFmpeg with [`useSystemBinaries()`](usage.md) if the bundled one doesn't have it.

## Temporary files

Shaka Streamer writes its whole output locally before it's uploaded. Inputs from remote disks are downloaded here too.

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `temporary_files_root` | `STREAMER_TEMPORARY_FILES_ROOT` | `storage/app/streamer/temp` | Where inputs and output are written. Needs room for every quality of every job running at the same time. |
| `cache_files_root` | `STREAMER_CACHE_FILES_ROOT` | `/dev/shm` | Where encryption keys are written. A RAM disk keeps keys off the physical disk. Set it to an empty string to use `temporary_files_root`. |

### Storage guards

A job that runs out of space fails halfway, after hours of encoding. These floors stop it before it starts, with a `Foxws\Streamer\Exceptions\InsufficientStorageException`. Both are off by default.

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `temporary_files_min_free` | `STREAMER_TEMPORARY_MIN_FREE` | `0` | Minimum free bytes in `temporary_files_root`. |
| `cache_files_min_free` | `STREAMER_CACHE_MIN_FREE` | `0` | Minimum free bytes in `cache_files_root`. |

Encoding can make the output much smaller or larger than the input, depending on the qualities and codecs. So unlike Laravel Shaka, there's no check based on the input size, only these fixed floors. Set the floor to the largest output you expect.

The two roots have separate floors because they're often very different sizes. `/dev/shm` may only have a few dozen MB, while `temporary_files_root` may have many GB.

## Uploads

These apply when the target is an S3 disk. On a local disk, files are moved with `rename()` instead.

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `concurrency_workers` | `STREAMER_CONCURRENCY_WORKERS` | `30` | How many files upload at the same time. |
| `multipart_threshold` | `STREAMER_MULTIPART_THRESHOLD` | `67108864` (64 MB) | Files this size or larger use a multipart upload. |
| `multipart_part_size` | `STREAMER_MULTIPART_PART_SIZE` | `16777216` (16 MB) | Size of each part. At least 5 MB. |
| `multipart_concurrency` | `STREAMER_MULTIPART_CONCURRENCY` | `5` | Parts uploaded at the same time, per file. |

A single upload is limited to 5 GB, so multipart is needed for larger files. It's also faster for big files, because parts go up in parallel. A failed multipart upload is cancelled, so its parts don't stay in the bucket.

The disk's own options are kept, such as `CacheControl` from `options` in `config/filesystems.php`.

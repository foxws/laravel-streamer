---
sidebar_position: 5
---

# Configuration

Laravel Shaka Streamer can be configured via the `config/streamer.php` file.

## Publishing Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="streamer-config"
```

## Configuration Options

### Streamer Binary

Configure the Shaka Streamer binary:

```php
'streamer' => [
    'streamer_binary' => env('STREAMER_BINARY', 'shaka-streamer'),
],
```

**Environment Variable:**

```env
STREAMER_BINARY=shaka-streamer
```

### Force Generic Input

Use generic input paths instead of absolute paths:

```php
'force_generic_input' => env('STREAMER_FORCE_GENERIC_INPUT', true),
```

**Environment Variable:**

```env
STREAMER_FORCE_GENERIC_INPUT=true
```

### Timeout

Set the maximum execution time for streaming operations:

```php
'timeout' => env('STREAMER_TIMEOUT', 60 * 60 * 4), // 4 hours in seconds
```

**Environment Variable:**

```env
STREAMER_TIMEOUT=14400
```

**Considerations:**

- Longer videos require more time
- 4K content takes significantly longer than 1080p
- Multiple quality variants multiply processing time
- Consider your server's PHP `max_execution_time` setting

### Logging

Enable logging to track streaming operations:

```php
'log_channel' => env('STREAMER_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
```

**Environment Variables:**

```env
# Use default log channel
STREAMER_LOG_CHANNEL=stack

# Use custom channel
STREAMER_LOG_CHANNEL=streamer
```

**Custom Log Channel:**
Define a custom channel in `config/logging.php`:

```php
'channels' => [
    'streamer' => [
        'driver' => 'daily',
        'path' => storage_path('logs/streamer.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

### Temporary Files

Configure where temporary files are stored:

```php
'temporary_files_root' => env('STREAMER_TEMPORARY_FILES_ROOT', storage_path('app/streamer/temp')),
```

**Environment Variable:**

```env
STREAMER_TEMPORARY_FILES_ROOT=/tmp/streamer
```

**Considerations:**

- Remote files (S3, etc.) are copied here before processing
- Ensure sufficient disk space
- Clean up regularly
- Use standard disk (not RAM) to avoid consuming memory

### Cache Files

Configure location for cache files (encryption keys, manifests, etc.):

```php
'cache_files_root' => env('STREAMER_CACHE_FILES_ROOT', '/dev/shm'),
```

**Environment Variable:**

```env
STREAMER_CACHE_FILES_ROOT=/dev/shm
```

**Note:** Using `/dev/shm` (RAM disk) provides better performance for small files but requires sufficient RAM.

### Storage Space Guards

Fail fast with a clear exception instead of a job dying mid-streaming when a
storage-constrained root runs low on space.

```php
'temporary_files_min_free' => env('STREAMER_TEMPORARY_MIN_FREE', 0),
'cache_files_min_free' => env('STREAMER_CACHE_MIN_FREE', 0),
```

**Environment Variables:**

```env
STREAMER_TEMPORARY_MIN_FREE=1073741824   # 1 GiB floor on temporary_files_root
STREAMER_CACHE_MIN_FREE=10485760         # 10 MiB floor on cache_files_root
```

Both are disabled by default (`0`), and kept independent of each other on
purpose: `cache_files_root` is often a much smaller mount (e.g. `/dev/shm`)
than `temporary_files_root`, so a single shared floor can't meaningfully
protect both at once. Both throw `Foxws\Streamer\Exceptions\InsufficientStorageException`.

Streamer transcodes via ffmpeg, so output size does **not** track input size
closely — encoding down to delivery bitrates can shrink a source
dramatically. There is no job-size-aware check here for that reason;
`temporary_files_min_free` is a flat safety net, not a per-job estimate.

#### Example: Podman tmpfs for `cache_files_root`

Because Streamer's `temporary_files_root` footprint isn't predictable from
the input file size (see above), putting it on a size-limited tmpfs is
riskier than putting it on a regular disk-backed volume — prefer disk for
`temporary_files_root`, per the note under [Temporary Files](#temporary-files) above.

`cache_files_root` (manifests/keys) is a safer fit for a RAM disk, since it
only holds small files. If you're running queue workers in Podman:

```ini
# horizon.container (podman quadlet)
[Container]
...
ShmSize=128m
```

```env
STREAMER_CACHE_FILES_ROOT=/dev/shm
STREAMER_CACHE_MIN_FREE=10485760   # 10 MiB - keep this well under ShmSize
```

### Codecs & Segment Duration

Default audio/video codecs and segment duration, overridable per-stream when
adding streams:

```php
'audio_codecs' => env('STREAMER_AUDIO_CODECS', 'aac'),
'video_codecs' => env('STREAMER_VIDEO_CODECS', 'h264'),
'segment_duration' => env('STREAMER_SEGMENT_DURATION', 6),
```

**Environment Variables:**

```env
STREAMER_AUDIO_CODECS=aac,opus
STREAMER_VIDEO_CODECS=hw:h264,hw:vp9
STREAMER_SEGMENT_DURATION=6
```

Prefix a video codec with `hw:` for hardware-accelerated encoding (e.g.
`hw:h264`).

### Hardware Acceleration

Set a hardware acceleration API for video encoding (`vaapi`, `nvenc`,
`videotoolbox`, `qsv`). Leave unset for software encoding.

```php
'hwaccel_api' => env('STREAMER_HWACCEL_API', null),
```

```env
STREAMER_HWACCEL_API=vaapi
```

### Extra Input Arguments

Additional raw arguments passed directly to the packager's input, useful for
advanced scenarios such as custom demuxer flags.

```php
'extra_input_args' => env('STREAMER_EXTRA_INPUT_ARGS', null),
```

### Streamer Options

Additional configuration merged into the Shaka Streamer pipeline config —
see the [Shaka Streamer configuration fields](https://shaka-project.github.io/shaka-streamer/configuration_fields.html).

```php
'streamer_options' => [],
```

### Concurrency Workers

Maximum number of concurrent S3 uploads when copying streamed files to an
S3-backed disk (ignored for local disks). Each in-flight upload holds an open
file stream, so memory usage scales with this value.

```php
'concurrency_workers' => env('STREAMER_CONCURRENCY_WORKERS', 30),
```

```env
STREAMER_CONCURRENCY_WORKERS=30
```

## Environment Configuration

Example `.env` configuration:

```env
STREAMER_BINARY=shaka-streamer
STREAMER_TIMEOUT=14400
STREAMER_LOG_CHANNEL=streamer
STREAMER_TEMPORARY_FILES_ROOT=/tmp/streamer
STREAMER_CACHE_FILES_ROOT=/dev/shm
STREAMER_FORCE_GENERIC_INPUT=true
STREAMER_TEMPORARY_MIN_FREE=1073741824
STREAMER_CACHE_MIN_FREE=10485760
```

## Verification

After configuration, verify your setup:

```bash
php artisan streamer:info
```

This command checks:

- Binary exists and is executable
- Can retrieve version information
- Configuration is properly set up
- Logger status

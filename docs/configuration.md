---
section: Reference
order: 2
---

# Configuration

You configure this package through the `config/streamer.php` file.

## Publishing the configuration file

Publish it with:

```bash
php artisan vendor:publish --tag="streamer-config"
```

## Configuration options

### Streamer binary

Set the path to the Shaka Streamer binary:

```php
'streamer' => [
    'streamer_binary' => env('STREAMER_BINARY', 'shaka-streamer'),
],
```

**Environment Variable:**

```env
STREAMER_BINARY=shaka-streamer
```

### Force generic input

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

**Things that affect how long packaging takes:**

- Longer videos need more time
- 4K content takes much longer than 1080p
- Each extra quality variant adds to the processing time
- Your server's PHP `max_execution_time` setting also matters

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

**Custom log channel:**
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

### Temporary files

Set where temporary files are stored:

```php
'temporary_files_root' => env('STREAMER_TEMPORARY_FILES_ROOT', storage_path('app/streamer/temp')),
```

**Environment Variable:**

```env
STREAMER_TEMPORARY_FILES_ROOT=/tmp/streamer
```

**Notes:**

- Remote files (from S3 and similar) are copied here before processing
- Make sure there's enough disk space
- Clean this directory up regularly
- Use a regular disk here, not RAM, so you don't eat into memory

### Cache files

Set where cache files (encryption keys, manifests, and so on) are stored:

```php
'cache_files_root' => env('STREAMER_CACHE_FILES_ROOT', '/dev/shm'),
```

**Environment Variable:**

```env
STREAMER_CACHE_FILES_ROOT=/dev/shm
```

**Note:** `/dev/shm` (a RAM disk) is faster for small files, but it needs enough RAM available to hold them.

### Storage space guards

These settings let you fail fast with a clear error instead of having a job
die partway through because a storage location ran out of space.

```php
'temporary_files_min_free' => env('STREAMER_TEMPORARY_MIN_FREE', 0),
'cache_files_min_free' => env('STREAMER_CACHE_MIN_FREE', 0),
```

**Environment Variables:**

```env
STREAMER_TEMPORARY_MIN_FREE=1073741824   # 1 GiB floor on temporary_files_root
STREAMER_CACHE_MIN_FREE=10485760         # 10 MiB floor on cache_files_root
```

Both are off by default (`0`), and they're kept independent on purpose:
`cache_files_root` is often a much smaller mount (e.g. `/dev/shm`) than
`temporary_files_root`, so one shared floor couldn't protect both properly.
Both throw `Foxws\Streamer\Exceptions\InsufficientStorageException` when
triggered.

Streamer encodes via ffmpeg, so the output size doesn't closely track the
input size — encoding down to delivery bitrates can shrink a file a lot.
Because of that, there's no check here that estimates space needed per job;
`temporary_files_min_free` is just a flat safety net, not a per-job estimate.

#### Example: Podman tmpfs for `cache_files_root`

Since `temporary_files_root`'s space usage isn't predictable from the input
file size (see above), putting it on a size-limited tmpfs is riskier than
putting it on a regular disk-backed volume — prefer disk for
`temporary_files_root`, as noted under [Temporary files](#temporary-files) above.

`cache_files_root` (which only holds manifests and keys) is a safer fit for
a RAM disk, since those files are small. If you're running queue workers in
Podman:

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

### Codecs & segment duration

These set the default audio/video codecs and segment duration. You can
override any of them for an individual stream when you add it:

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

Prefix a video codec with `hw:` to use hardware-accelerated encoding (e.g.
`hw:h264`).

### Hardware acceleration

Set which hardware acceleration API to use for video encoding — `vaapi`,
`nvenc`, `videotoolbox`, or `qsv`. Leave it unset to use software encoding
instead.

```php
'hwaccel_api' => env('STREAMER_HWACCEL_API', null),
```

```env
STREAMER_HWACCEL_API=vaapi
```

### Extra input arguments

Raw arguments passed directly to the packager's input. Useful for advanced
scenarios, such as custom demuxer flags.

```php
'extra_input_args' => env('STREAMER_EXTRA_INPUT_ARGS', null),
```

### Streamer options

Extra configuration merged directly into the Shaka Streamer pipeline config —
see the [Shaka Streamer configuration fields](https://shaka-project.github.io/shaka-streamer/configuration_fields.html)
for what's available.

```php
'streamer_options' => [],
```

### Concurrency workers

The maximum number of S3 uploads that can run at once when copying packaged
files to an S3-backed disk (this is ignored for local disks). Each upload in
progress holds an open file stream, so memory usage grows with this value.

```php
'concurrency_workers' => env('STREAMER_CONCURRENCY_WORKERS', 30),
```

```env
STREAMER_CONCURRENCY_WORKERS=30
```

## Environment configuration

An example `.env` configuration:

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

After configuring the package, check that everything works:

```bash
php artisan streamer:info
```

This command checks that:

- The binary exists and is executable
- Version information can be retrieved
- The configuration is set up correctly
- The logger is working

---
section: Reference
order: 1
---

# Configuration

Publish the config file to change the defaults:

```bash
php artisan vendor:publish --tag="streamer-config"
```

Every option can also be set in your `.env`. Temporary files, logging, S3 uploads, and the ffmpeg and ffprobe paths come from laravel-media's `config/media.php`.

## Shaka Streamer

| Option | `.env` | Default | What it does |
| --- | --- | --- | --- |
| `binary` | `STREAMER_BINARY` | `shaka-streamer` | The Shaka Streamer binary to run, from your `PATH` or a full path. |
| `system_binaries` | `STREAMER_SYSTEM_BINARIES` | `false` | Run the FFmpeg and Shaka Packager on the `PATH` instead of the ones from `shaka-streamer-binaries`. |
| `packager` | `STREAMER_PACKAGER_BINARY` | none | A full path to `packager`, put first in the `PATH` with system binaries. |
| `timeout` | `STREAMER_TIMEOUT` | `14400` | How long one run may take, in seconds. |

## Encoding defaults

The builder's methods override them for that run.

| Option | `.env` | Default | What it does |
| --- | --- | --- | --- |
| `video_codecs` | `STREAMER_VIDEO_CODECS` | `h264` | Comma-separated video codecs, e.g. `h264,vp9`. Prefix `hw:` for hardware encoding. |
| `audio_codecs` | `STREAMER_AUDIO_CODECS` | `aac` | Comma-separated audio codecs, e.g. `aac,opus`. |
| `segment_duration` | `STREAMER_SEGMENT_DURATION` | `6` | Seconds per segment. |
| `hardware_acceleration` | `STREAMER_HARDWARE_ACCELERATION` | none | The API for `hw:` codecs, e.g. `vaapi`, `nvenc` or `videotoolbox`. |
| `ffmpeg_input_args` | `STREAMER_FFMPEG_INPUT_ARGS` | none | FFmpeg arguments for every input. |

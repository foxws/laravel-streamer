# Shaka Streamer Migration Guide

## Overview

This document describes the conversion of Laravel Streamer from **Shaka Packager** to **Shaka Streamer**. The fluent API remains unchanged for end users, but the internal configuration format has been adapted to match Shaka Streamer's config-based approach.

## Key Changes

### 1. CommandBuilder Refactored

**Location:** [src/Support/CommandBuilder.php](src/Support/CommandBuilder.php)

**Previous:** Built CLI command strings for Shaka Packager
**New:** Builds configuration arrays for Shaka Streamer

**Config Structure:**
Shaka Streamer expects two separate configs:

```php
[
    'input_config' => [
        'inputs' => [
            [
                'input_type' => 'file',
                'name' => '/path/to/video.mp4',
                'media_type' => 'video'
            ]
        ]
    ],
    'pipeline_config' => [
        'streaming_mode' => 'vod',
        'manifest_format' => ['dash', 'hls'],
        'dash_output' => 'manifest.mpd',
        'hls_output' => 'master.m3u8',
        'segment_size' => 10
    ]
]
```

**Key Methods:**

- `build()` → Returns both `input_config` and `pipeline_config`
- `buildInputConfig()` → Creates InputConfig for Shaka Streamer
- `buildPipelineConfig()` → Creates PipelineConfig for Shaka Streamer
- `buildManifestFormats()` → Determines which formats to generate

### 2. ShakaStreamer Class Updated

**Location:** [src/Support/ShakaStreamer.php](src/Support/ShakaStreamer.php)

**New Method:**

```php
public function packageWithConfig(array $config): string
```

This method accepts the configuration array from CommandBuilder and invokes Shaka Streamer.

**Note:** The actual Shaka Streamer invocation needs to be implemented based on how Shaka Streamer is called (Python API, CLI wrapper, etc).

### 3. Streamer Class Updated

**Location:** [src/Support/Streamer.php](src/Support/Streamer.php)

**Changes:**

- `getCommand()` now returns `array` instead of `string`
- `export()` uses `packageWithConfig()` instead of `command()`
- `packageWithBuilder()` uses `packageWithConfig()` instead of `command()`

### 4. MediaExporter Updated

**Location:** [src/Exporters/MediaExporter.php](src/Exporters/MediaExporter.php)

- `getCommand()` return type changed from `string` to `array`
- Updated docblock to reflect "config" instead of "command"

## Fluent API Compatibility

✅ **User-facing API unchanged**

Users continue to use the same fluent interface:

```php
Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->export()
    ->toDisk('export')
    ->save();
```

## Configuration Reference

Shaka Streamer documentation: https://shaka-project.github.io/shaka-streamer/configuration_fields.html

### Input Config Fields

- `input_type`: 'file', 'looped_file', 'webcam', 'microphone', 'external_command'
- `name`: File path or device identifier
- `media_type`: 'audio', 'video', 'text'
- `track_num`: Track number (defaults to 0)
- Optional: `filters`, `frame_rate`, `resolution`, `language`

### Pipeline Config Fields

- `streaming_mode`: 'vod' or 'live'
- `manifest_format`: List of ['dash', 'hls']
- `dash_output`: Output path for DASH manifest
- `hls_output`: Output path for HLS master playlist
- `segment_size`: Segment duration in seconds
- `audio_codecs`: List of ['aac', 'opus', 'ac3', 'eac3', 'flac']
- `video_codecs`: List of ['h264', 'vp9', 'av1', 'hevc']
- `encryption`: Encryption configuration object

## Next Steps

1. **Implement `packageWithConfig()` in ShakaStreamer**
    - Determine how to invoke Shaka Streamer (Python subprocess, direct binary, etc.)
    - Handle configuration serialization (JSON/YAML)
    - Capture and parse output

2. **Test with actual Shaka Streamer**
    - Verify config structure compatibility
    - Test with sample media files
    - Validate output manifests and segments

3. **Handle resolution and codec selection**
    - Map bandwidth options to codec parameters
    - Support quality levels in pipeline config
    - Implement adaptive bitrate configurations

4. **Error handling**
    - Wrap Shaka Streamer errors appropriately
    - Add validation for incompatible config combinations
    - Provide helpful error messages

## Architecture Comparison

### Shaka Packager (Old)

```
Fluent API → CommandBuilder → CLI Args → packager binary
```

### Shaka Streamer (New)

```
Fluent API → CommandBuilder → Config Array → Shaka Streamer API/CLI
```

## Encryption Configuration

The encryption options have been refactored to match Shaka Streamer's format:

**Shaka Streamer expects (raw key mode):**

```php
'encryption' => [
    'enable' => true,
    'encryption_mode' => 'raw',
    'protection_systems' => ['Widevine', 'PlayReady'],
    'keys' => [
        [
            'key_id' => '...',
            'key' => '...'
        ]
    ]
]
```

## Notes

- Shaka Streamer handles both VOD and live streaming with the `streaming_mode` config
- Resolution presets (720p, 480p, etc.) are built-in to Shaka Streamer
- The `segment_per_file` option is required for live streams
- Default segment size is 10 seconds, adjustable via `segment_size`

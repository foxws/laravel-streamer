# Quick Reference for Copilot

## 30-Second Overview

**Laravel Streamer** generates DASH/HLS streams using **Shaka Streamer** (Python 3).

```php
// User code (fluent API)
Streamer::open('video.mp4')
    ->addVideoStream('video.mp4', 'video.mp4')
    ->addAudioStream('video.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->export()
    ->save();

// Behind the scenes:
// 1. CommandBuilder → generates Shaka Streamer config array
// 2. ShakaStreamer → validates, verifies, invokes Python
// 3. python3 -m streamer.main --config /tmp/config.json
```

## File Locations

| File                              | Purpose                                        |
| --------------------------------- | ---------------------------------------------- |
| `src/Support/CommandBuilder.php`  | Builds Shaka Streamer config from fluent calls |
| `src/Support/ShakaStreamer.php`   | Invokes Python3 module with validation         |
| `src/Support/Streamer.php`        | Main orchestrator (fluent API)                 |
| `src/Exporters/MediaExporter.php` | Uses CommandBuilder & ShakaStreamer            |
| `config/streamer.php`             | Configuration template                         |

## Key Methods

### CommandBuilder

```php
CommandBuilder::make()
    ->addVideoStream($input, $output, $options)
    ->addAudioStream($input, $output, $options)
    ->withMpdOutput($path)
    ->withHlsMasterPlaylist($path)
    ->withSegmentDuration($seconds)
    ->withEncryption($config)
    ->withOption($key, $value)
    ->build() // Returns: ['input_config' => [...], 'pipeline_config' => [...]]
```

### ShakaStreamer

```php
$streamer = ShakaStreamer::create($logger, $config);
$streamer->packageWithConfig($config);
$streamer->verifyInstallation();
$streamer->setPythonBinary('python3');
$streamer->setTimeout(3600);
```

### Streamer

```php
Streamer::open($file)
    ->addVideoStream($in, $out, $options)
    ->addAudioStream($in, $out, $options)
    ->withMpdOutput($path)
    ->withHlsMasterPlaylist($path)
    ->export() // Returns PackagerResult
    ->save()
```

## Config Structure

```php
[
    'input_config' => [
        'inputs' => [
            ['input_type' => 'file', 'name' => 'video.mp4', 'media_type' => 'video'],
            ['input_type' => 'file', 'name' => 'video.mp4', 'media_type' => 'audio']
        ]
    ],
    'pipeline_config' => [
        'streaming_mode' => 'vod',
        'manifest_format' => ['dash', 'hls'],
        'dash_output' => 'manifest.mpd',
        'hls_output' => 'master.m3u8',
        'segment_size' => 10.0
    ]
]
```

## Execution Flow

```
User API Call
    ↓
CommandBuilder::build() → config array
    ↓
Streamer::export()
    ↓
ShakaStreamer::packageWithConfig($config)
    ├─ validateConfig() → checks structure & files
    ├─ verifyInstallation() → checks python3 -m streamer.main
    ├─ createConfigFile() → writes /tmp/shaka_config_XXXXX
    ├─ invokeStreamer() → runs python3 -m streamer.main --config
    └─ cleanup temp file
    ↓
PackagerResult (output & paths)
```

## Common Errors

| Error                             | Cause                            | Solution                     |
| --------------------------------- | -------------------------------- | ---------------------------- |
| "Shaka Streamer is not installed" | `python3 -m streamer.main` fails | `pip install shaka-streamer` |
| "Input file not found"            | File in config doesn't exist     | Check file path exists       |
| "Invalid manifest format"         | Format not 'dash' or 'hls'       | Use valid format             |
| "Permission denied"               | Can't read/write files           | Check file permissions       |
| "Exit code X"                     | Process failed                   | Check stderr in error logs   |

## Testing

```php
// Verify installation
$streamer = ShakaStreamer::create();
$streamer->verifyInstallation(); // throws if not ready

// Test config
$config = Streamer::open('video.mp4')
    ->addVideoStream('video.mp4', 'video.mp4')
    ->getCommand(); // Returns config array

dd($config);
```

## Environment Variables

```bash
SHAKA_PYTHON_BINARY=python3      # Override Python path
SHAKA_TIMEOUT=3600                # Timeout in seconds (default 1 hour)
```

## Documentation

- **Architecture**: See [SHAKA_STREAMER_COMPLETE.md](../SHAKA_STREAMER_COMPLETE.md)
- **Implementation**: See [IMPLEMENTATION_NOTES.md](../IMPLEMENTATION_NOTES.md)
- **Migration**: See [SHAKA_STREAMER_MIGRATION.md](../SHAKA_STREAMER_MIGRATION.md)
- **Examples**: See [SHAKA_STREAMER_CONFIG_EXAMPLES.php](../SHAKA_STREAMER_CONFIG_EXAMPLES.php)

## Installation

```bash
# End user installation
pip install shaka-streamer

# Verify
python3 -m streamer.main --version
```

## Key Points for Copilot

1. **100% backward compatible** - Fluent API unchanged
2. **Config-based** - Uses JSON files, not CLI args
3. **Python3 integration** - Via pip, not binary
4. **Validation first** - Validates before executing
5. **Clean errors** - Helpful error messages
6. **Full logging** - Debug, info, error levels
7. **Temp files** - Auto cleanup in finally block
8. **No breaking changes** - Safe to modify

---

Last updated: February 5, 2026

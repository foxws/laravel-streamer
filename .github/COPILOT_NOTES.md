# Copilot Context & Notes

**Last Updated:** February 5, 2026
**Status:** Shaka Streamer Integration Complete ✅

## Project Overview

**Laravel Streamer** - A Laravel package for media streaming with DASH/HLS output generation.

**Recent Major Change:** Migration from Shaka Packager to Shaka Streamer (February 2026)

## Architecture Summary

### Current Stack

- **Framework:** Laravel 11.x
- **Streaming Engine:** Shaka Streamer (Python 3.8+)
- **Installation:** Global pip (`pip install shaka-streamer`)
- **Invocation:** `python3 -m streamer.main --config {file.json}`
- **Process Management:** Laravel's Process Facade

### File Structure

```
src/
├── Support/
│   ├── CommandBuilder.php       (298 lines) - Builds Shaka Streamer config
│   ├── ShakaStreamer.php        (327 lines) - Python3 wrapper & invocation
│   ├── Streamer.php             (Main API orchestrator)
│   └── Stream.php
├── Exporters/
│   └── MediaExporter.php        (Uses CommandBuilder & ShakaStreamer)
├── Filesystem/
│   ├── Disk.php
│   └── Media.php
└── ...

config/
└── streamer.php                 (Configuration template)

docs/
├── ARCHITECTURE.md
└── CONFIGURATION.md
```

## Key Implementation Details

### Shaka Streamer Migration (2026)

#### What Changed

- **Input:** CLI arguments → JSON config file
- **Invocation:** `packager` binary → `python3 -m streamer.main`
- **Configuration:** Flat array → Nested InputConfig + PipelineConfig
- **API:** String output → Array output from CommandBuilder

#### What Stayed the Same

- ✅ 100% backward compatible fluent API
- ✅ Same method names (addVideoStream, withMpdOutput, etc.)
- ✅ Same return types from user perspective
- ✅ Same error handling patterns

### Core Classes

#### CommandBuilder

**Location:** `src/Support/CommandBuilder.php`

Builds configuration for Shaka Streamer:

```php
$builder = CommandBuilder::make();
$builder
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->withSegmentDuration(10);

$config = $builder->buildArray();
// Returns: ['input_config' => [...], 'pipeline_config' => [...]]
```

**Key Methods:**

- `buildArray()` / `build()` - Returns Shaka Streamer config
- `buildInputConfig()` - Generates InputConfig
- `buildPipelineConfig()` - Generates PipelineConfig
- `buildManifestFormats()` - Determines dash/hls output

#### ShakaStreamer

**Location:** `src/Support/ShakaStreamer.php`

Invokes Shaka Streamer via Python module:

```php
$streamer = ShakaStreamer::create();
$output = $streamer->packageWithConfig($config);
```

**Key Methods:**

- `packageWithConfig(array $config): string` - Main entry point
- `verifyInstallation(): void` - Checks if Shaka Streamer is installed
- `createConfigFile(array $config): string` - Creates temp JSON file
- `invokeStreamer(string $configFile): string` - Runs Python module
- `validateConfig(array $config): void` - Pre-execution validation
- `handleProcessFailure($result): void` - Error mapping

**Configuration:**

```php
// In config/laravel-streamer.php
'streamer' => [
    'python_binary' => env('SHAKA_PYTHON_BINARY', 'python3'),
    'timeout' => env('SHAKA_TIMEOUT', 3600), // 1 hour
],
```

#### Streamer (Main Orchestrator)

**Location:** `src/Support/Streamer.php`

Orchestrates the streaming workflow:

```php
$result = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->withMpdOutput('manifest.mpd')
    ->export()
    ->toDisk('export')
    ->save();
```

**Key Methods:**

- `export()` - Calls `ShakaStreamer::packageWithConfig()`
- `getCommand()` - Returns config array for debugging
- `packageWithBuilder()` - Uses custom CommandBuilder

## Configuration Flow

```
User Code (Fluent API)
    ↓
CommandBuilder::addVideoStream() → stores stream config
    ↓
export()
    ↓
CommandBuilder::build() → generates Shaka Streamer config array
    ↓
ShakaStreamer::packageWithConfig($config)
    ↓
validateConfig() → checks structure & files
    ↓
verifyInstallation() → checks python3 & shaka-streamer
    ↓
createConfigFile() → writes JSON to /tmp/shaka_config_XXXXX
    ↓
invokeStreamer() → runs: python3 -m streamer.main --config {file}
    ↓
Cleanup temp file
    ↓
Return output
```

## Input/Pipeline Config Structure

### InputConfig

```php
'input_config' => [
    'inputs' => [
        [
            'input_type' => 'file',        // or 'looped_file', 'webcam', etc.
            'name' => '/path/to/input.mp4',
            'media_type' => 'video'        // 'video', 'audio', 'text'
        ]
    ]
]
```

### PipelineConfig

```php
'pipeline_config' => [
    'streaming_mode' => 'vod',             // 'vod' or 'live'
    'manifest_format' => ['dash', 'hls'],  // Output formats
    'dash_output' => 'manifest.mpd',
    'hls_output' => 'master.m3u8',
    'segment_size' => 10.0,                // Segment duration in seconds
    // Optional:
    'resolutions' => ['1080p', '720p'],
    'video_codecs' => ['h264', 'vp9'],
    'audio_codecs' => ['aac', 'opus'],
    'encryption' => [...]                  // Encryption config
]
```

## Error Handling

### Validation Errors (validateConfig)

- Missing input_config or pipeline_config
- Missing inputs array
- Missing required fields in inputs
- Input files don't exist on filesystem
- Invalid manifest formats

### Installation Errors (verifyInstallation)

- Python 3 not available
- Shaka Streamer not installed
- Helpful message: "pip install shaka-streamer"

### Process Errors (handleProcessFailure)

- File not found → "Input file not found"
- Permission denied → "Permission denied accessing files"
- Invalid configuration → "Invalid Shaka Streamer configuration"
- Timeout → Throws RuntimeException
- Other errors → Full exit code + stderr

## Logging

All operations log at appropriate levels:

### Debug Level

```php
$logger->debug('Configuration validated successfully');
$logger->debug('Shaka Streamer verified', ['version_output' => '...']);
$logger->debug('Created temporary config file', ['path' => '...', 'size' => 1234]);
$logger->debug('Invoking Shaka Streamer', ['command' => '...', 'timeout' => 3600]);
```

### Info Level

```php
$logger->info('Starting Shaka Streamer packaging', ['inputs' => 2, 'outputs' => 2]);
$logger->info('Shaka Streamer completed successfully');
```

### Error Level

```php
$logger->error('Shaka Streamer failed', [
    'exit_code' => 1,
    'stderr' => 'Error message',
    'stdout' => 'Output'
]);
```

## Important Notes for Future Development

### 1. When Modifying CommandBuilder

- Ensure `build()` method always returns `['input_config' => [...], 'pipeline_config' => [...]]`
- Validate all inputs are included in input_config
- Keep method names unchanged for backward compatibility
- Test with both DASH and HLS outputs

### 2. When Modifying ShakaStreamer

- Never skip validation - it catches issues early
- Always use try/finally for temp file cleanup
- Keep error messages user-friendly
- Test with missing Shaka Streamer installation
- Test with invalid config structures

### 3. When Updating Streamer

- Maintain fluent API compatibility
- Never break method chaining
- Keep export() behavior consistent
- Test with MediaExporter integration

### 4. Configuration Updates

- Update both code examples and docs/CONFIGURATION.md
- Remember: config changes affect every user
- Document new options clearly
- Provide sensible defaults

## Installation & Testing

### End User Installation

```bash
pip install shaka-streamer
python3 -m streamer.main --version
```

### Verification in Code

```php
$streamer = ShakaStreamer::create();
try {
    $streamer->verifyInstallation();
    echo "Ready to go!";
} catch (\RuntimeException $e) {
    echo "Install with: pip install shaka-streamer";
}
```

### Testing Checklist

- [ ] Configuration validation
- [ ] JSON file creation & cleanup
- [ ] Installation verification
- [ ] Input file checking
- [ ] Process invocation
- [ ] Timeout handling
- [ ] Error mapping
- [ ] Logging at all levels
- [ ] Backward compatibility

## Documentation Files

| File                                 | Purpose                                             |
| ------------------------------------ | --------------------------------------------------- |
| `SHAKA_STREAMER_COMPLETE.md`         | Final implementation summary & production readiness |
| `IMPLEMENTATION_NOTES.md`            | Complete technical guide with examples              |
| `SHAKA_STREAMER_MIGRATION.md`        | Architecture overview & migration notes             |
| `SHAKA_STREAMER_CONFIG_EXAMPLES.php` | 5 real-world configuration examples                 |
| `docs/ARCHITECTURE.md`               | Overall system design                               |
| `docs/CONFIGURATION.md`              | User-facing configuration guide                     |

## Common Tasks

### Debug Command Generation

```php
$config = Streamer::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')
    ->getCommand(); // Returns config array

dd($config); // View the generated config
```

### Test with Custom Timeout

```php
$streamer = ShakaStreamer::create()
    ->setTimeout(7200); // 2 hours

$result = $streamer->packageWithConfig($config);
```

### Test Installation

```php
$streamer = ShakaStreamer::create();
$streamer->verifyInstallation(); // Throws if not installed
```

### Manual Config Test

```php
$config = [
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
        'manifest_format' => ['dash'],
        'dash_output' => '/tmp/manifest.mpd'
    ]
];

$streamer = ShakaStreamer::create();
$output = $streamer->packageWithConfig($config);
```

## Known Limitations

1. **File Paths:** Only supports local file paths (not URLs or streams) for inputs
2. **Python Version:** Requires Python 3.8+
3. **Dependencies:** Requires FFmpeg + Shaka Streamer installed
4. **Timeout:** Long videos may exceed 1-hour default timeout
5. **Temp Files:** Uses system temp directory (may cause issues on some systems)

## Future Enhancements

Potential areas for improvement:

- [ ] Support for Widevine encryption configuration
- [ ] Streaming from URLs/streams directly
- [ ] Progress callbacks during packaging
- [ ] Queue integration for long operations
- [ ] Docker-based invocation option
- [ ] Configuration caching for repeated operations
- [ ] Multi-bitrate preset configurations

## References

- **Official Docs:** https://shaka-project.github.io/shaka-streamer/
- **Config Reference:** https://shaka-project.github.io/shaka-streamer/configuration_fields.html
- **Prerequisites:** https://shaka-project.github.io/shaka-streamer/prerequisites.html

## Version History

**v2.0.0 (February 5, 2026)** - Shaka Streamer Integration

- Migrated from Shaka Packager to Shaka Streamer
- New config-based approach with JSON files
- Python 3 integration via pip
- Pre-execution validation
- Installation verification
- Comprehensive error handling

**v1.x.x** - Shaka Packager Era

- CLI-based invocation
- Direct binary execution
- Argument-based configuration

---

**For questions or updates, refer to the project documentation and test suite.**

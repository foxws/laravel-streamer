# Shaka Streamer Implementation Notes

## Current Status

✅ **Configuration Layer Complete**

- CommandBuilder refactored to generate Shaka Streamer config format
- Config structure validated against official documentation
- Fluent API preserved for backward compatibility

⚠️ **Pending: Streamer Invocation**

- `ShakaStreamer::packageWithConfig()` method skeleton added
- Actual Shaka Streamer invocation needs implementation

## Implementation Plan for `packageWithConfig()`

### Step 1: Determine Invocation Method

Shaka Streamer can be called in several ways:

#### Option A: Python Module (Recommended)

```php
// If Shaka Streamer is installed as a Python package
$pythonScript = <<<'PYTHON'
import json
import sys
from streamer.controller_node import ControllerNode

config = json.loads(sys.stdin.read())
controller = ControllerNode(config)
controller.start()
PYTHON;

$process = Process::input(json_encode($config))
    ->run(['python3', '-c', $pythonScript]);
```

#### Option B: Docker Container

```php
// If using Shaka Streamer via Docker
$process = Process::input(json_encode($config))
    ->run([
        'docker', 'run', '--rm',
        '-i',  // stdin
        '-v', getcwd() . ':/work',
        'shaka-project/shaka-streamer:latest',
        'shaka-streamer',
        '--input', '-',  // read from stdin
        '--config', '/work/config.json'
    ]);
```

#### Option C: CLI Wrapper

```php
// If Shaka Streamer has a CLI interface
$configFile = tempnam('/tmp', 'shaka_config_');
file_put_contents($configFile, json_encode($config));

$process = Process::run([
    'shaka-streamer',
    '--config', $configFile
]);

unlink($configFile);
```

### Step 2: Configuration Serialization

The config array must be serialized to pass to Shaka Streamer:

```php
public function packageWithConfig(array $config): string
{
    // Option 1: JSON (simplest)
    $configJson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // Option 2: YAML (if Shaka Streamer prefers it)
    // $configYaml = $this->arrayToYaml($config);

    // Option 3: Write to temporary file
    $configFile = tempnam(sys_get_temp_dir(), 'shaka_');
    file_put_contents($configFile, $configJson);

    try {
        // Invoke Shaka Streamer
        $result = $this->invokeStreamer($configFile);
        return $result;
    } finally {
        unlink($configFile);
    }
}
```

### Step 3: Handle Output

Shaka Streamer outputs progress and results via stdout/stderr:

```php
protected function invokeStreamer(string $configFile): string
{
    $process = Process::timeout($this->timeout)
        ->run(['shaka-streamer', '--config', $configFile]);

    if ($process->failed()) {
        if ($this->logger) {
            $this->logger->error('Shaka Streamer failed', [
                'stderr' => $process->errorOutput(),
                'stdout' => $process->output(),
            ]);
        }

        throw new RuntimeException(
            "Shaka Streamer failed: " . $process->errorOutput()
        );
    }

    if ($this->logger) {
        $this->logger->info('Shaka Streamer completed', [
            'output' => $process->output(),
        ]);
    }

    return $process->output();
}
```

## Integration Points to Check

### 1. Configuration Validation

Add validation before passing to Shaka Streamer:

```php
protected function validateConfig(array $config): bool
{
    $required = ['input_config', 'pipeline_config'];

    foreach ($required as $key) {
        if (!isset($config[$key])) {
            throw new InvalidArgumentException("Missing {$key}");
        }
    }

    // Validate input_config structure
    if (empty($config['input_config']['inputs'])) {
        throw new InvalidArgumentException('No inputs configured');
    }

    // Validate pipeline_config
    if (!isset($config['pipeline_config']['streaming_mode'])) {
        throw new InvalidArgumentException('Missing streaming_mode');
    }

    $validModes = ['vod', 'live'];
    if (!in_array($config['pipeline_config']['streaming_mode'], $validModes)) {
        throw new InvalidArgumentException('Invalid streaming_mode');
    }

    return true;
}
```

### 2. Error Handling

Map Shaka Streamer errors to package exceptions:

```php
protected function handleStreamerError(string $errorOutput): void
{
    if (strpos($errorOutput, 'No suitable writer found') !== false) {
        throw new MediaNotFoundException('Output format not supported');
    }

    if (strpos($errorOutput, 'Invalid packet size') !== false) {
        throw new InvalidStreamConfigurationException('Invalid configuration');
    }

    throw new PackagingException($errorOutput);
}
```

### 3. Progress Monitoring

Implement progress callbacks:

```php
protected function invokeStreamerWithProgress(string $configFile): string
{
    $process = Process::timeout($this->timeout)
        ->run(
            ['shaka-streamer', '--config', $configFile],
            function (string $type, string $buffer) {
                if ($this->logger) {
                    $this->logger->info("Shaka: {$buffer}");
                }

                // Parse progress from output
                if (preg_match('/(\d+)%/i', $buffer, $matches)) {
                    $progress = (int) $matches[1];
                    // Dispatch progress event
                    event(new ProgressUpdated($progress));
                }
            }
        );

    return $process->output();
}
```

## Testing Checklist

- [ ] Test with simple VOD (single resolution)
- [ ] Test with adaptive bitrate (multiple resolutions)
- [ ] Test DASH output generation
- [ ] Test HLS output generation
- [ ] Test with encryption enabled
- [ ] Test with different segment durations
- [ ] Test error handling (missing files, invalid config)
- [ ] Test with live streaming mode
- [ ] Test cleanup of temporary files
- [ ] Test logging at different levels
- [ ] Test with large files
- [ ] Test cancellation/timeout

## Configuration Reference

For the latest Shaka Streamer configuration options, see:
https://shaka-project.github.io/shaka-streamer/configuration_fields.html

Key sections:

- InputConfig: Describes input sources
- PipelineConfig: Streaming parameters, codecs, encryption
- BitrateConfig: Custom resolution/bitrate definitions

## Next Steps

1. **Choose invocation method** - Determine how Shaka Streamer will be called
2. **Implement packageWithConfig()** - Add the actual invocation logic
3. **Add validation** - Validate configurations before passing to Shaka Streamer
4. **Handle errors** - Map Shaka Streamer errors to package exceptions
5. **Test thoroughly** - Run against various input types and configurations
6. **Document** - Update user-facing documentation with new capabilities

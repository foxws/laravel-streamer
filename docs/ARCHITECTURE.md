# Architecture Overview

Laravel Shaka implements a clean, testable architecture based on the proven patterns used by PHP-FFmpeg and Laravel FFmpeg.

## Architecture Layers

### 1. Driver Layer (`ShakaPackager`)

The driver layer handles direct interaction with the Shaka Packager binary:

```php
namespace Foxws\Streamer\Support\Packager;

class ShakaPackager
{
    protected string $binaryPath;
    protected ?LoggerInterface $logger;
    protected int $timeout;

    // Binary execution
    public function command(string $command): string;

    // Version detection
    public function getVersion(): string;

    // Configuration
    public function setTimeout(int $timeout): self;
}
```

**Responsibilities:**

- Binary path detection and validation
- Command execution with timeout handling
- Process management via Laravel's Process facade
- Version checking
- Error handling and exceptions
- Logger integration

**Benefits:**

- Separation of concerns
- Easy to mock for testing
- Consistent error handling
- Centralized logging

### 2. Business Logic Layer (`Packager`)

The packager layer provides the high-level API:

```php
namespace Foxws\Streamer\Support\Packager;

class Packager
{
    protected ShakaPackager $driver;
    protected ?MediaCollection $mediaCollection;
    protected ?CommandBuilder $builder;

    // Media management
    public function open(MediaCollection $mediaCollection): self;

    // Stream configuration
    public function addVideoStream(string $input, string $output, array $options = []): self;
    public function addAudioStream(string $input, string $output, array $options = []): self;

    // Output configuration
    public function withMpdOutput(string $path): self;
    public function withHlsMasterPlaylist(string $path): self;

    // Execution
    public function export(): PackagerResult;
}
```

**Responsibilities:**

- Managing media collections
- Building commands via CommandBuilder
- Translating high-level API to binary commands
- Logging packaging operations
- Returning structured results

**Benefits:**

- Fluent, chainable API
- Business logic separate from binary execution
- Type-safe operations
- Structured result objects

### 3. Facade Layer (`Shaka` & `MediaOpenerFactory`)

The facade layer provides the Laravel-style interface:

```php
namespace Foxws\Streamer;

class Shaka
{
    protected ?Disk $disk;
    protected ?Packager $packager;
    protected ?MediaCollection $collection;

    // Disk management
    public function fromDisk(Filesystem|string $disk): self;
    public function openFromDisk(Filesystem|string $disk, $paths): self;

    // Media management
    public function open($paths): self;

    // Forwards all Packager methods
    public function __call($method, $arguments);
}
```

**Responsibilities:**

- Managing filesystem disks
- Opening media files
- Forwarding calls to Packager
- Providing convenient helpers

**Benefits:**

- Clean, intuitive API
- Laravel conventions
- Multiple disks support
- Method chaining

## Component Relationships

```
┌─────────────────────────────────────────────┐
│           Shaka (Facade)                    │
│  - Disk management                          │
│  - Media file opening                       │
│  - Method forwarding                        │
└────────────────┬────────────────────────────┘
                 │
                 ├──> MediaCollection (Media files)
                 │
                 v
┌─────────────────────────────────────────────┐
│           Packager (Business Logic)         │
│  - Stream configuration                     │
│  - Command building                         │
│  - Fluent API                              │
└────────────────┬────────────────────────────┘
                 │
                 ├──> CommandBuilder (Command construction)
                 ├──> Stream (Stream objects)
                 │
                 v
┌─────────────────────────────────────────────┐
│      ShakaPackager (Binary)           │
│  - Binary execution                         │
│  - Process management                       │
│  - Error handling                           │
└────────────────┬────────────────────────────┘
                 │
                 v
        [Shaka Packager Binary]
```

## Supporting Classes

### CommandBuilder

Builds packager command strings fluently:

```php
$builder = CommandBuilder::make()
    ->addVideoStream('input.mp4', 'video.mp4')
    ->addAudioStream('input.mp4', 'audio.mp4')
    ->withMpdOutput('manifest.mpd')
    ->withSegmentDuration(6);

$command = $builder->build();
```

### Stream

Represents a single stream configuration:

```php
$stream = Stream::video($media)
    ->setOutput('video.mp4')
    ->addOption('bandwidth', '5000000');

$commandString = $stream->toCommandString();
// "in=/path/to/input.mp4,stream=video,output=video.mp4,bandwidth=5000000"
```

### PackagerResult

Structured result from packaging operations:

```php
$result = $packager->export();

$output = $result->getOutput();
$metadata = $result->getMetadata();
$outputPath = $result->getMetadataValue('output_path');
```

### Media & MediaCollection

Represents input media files:

```php
$media = Media::make($disk, 'video.mp4');
$collection = MediaCollection::make([$media]);

$localPath = $media->getLocalPath();
$filename = $media->getFilename();
```

## Service Provider Registration

The package uses Laravel's service container for dependency injection:

```php
// ShakaServiceProvider.php

// Register driver
$this->app->singleton(ShakaPackager::class, function ($app) {
    $logger = $app->make('laravel-streamer-logger');
    $config = $app->make('laravel-streamer-configuration');

    return ShakaPackager::create($logger, $config);
});

// Register packager
$this->app->singleton(Packager::class, function ($app) {
    $driver = $app->make(ShakaPackager::class);
    $logger = $app->make('laravel-streamer-logger');

    return new Packager($driver, $logger);
});
```

## Error Handling

The package uses a clear exception hierarchy:

```php
try {
    $result = Streamer::open('input.mp4')->export();
} catch (ExecutableNotFoundException $e) {
    // Binary not found
} catch (RuntimeException $e) {
    // Command execution failed
} catch (InvalidArgumentException $e) {
    // Invalid input
}
```

## Testing Strategy

The architecture enables easy testing:

```php
// Mock the driver
$driver = Mockery::mock(ShakaPackager::class);
$driver->shouldReceive('command')->andReturn('success');

$packager = new Packager($driver);
$result = $packager->open($collection)->export();
```

## Extension Points

### Custom Drivers

Extend the driver for custom behavior:

```php
class CustomPackagerDriver extends ShakaPackager
{
    public function customOperation(array $options): string
    {
        $command = $this->buildCustomCommand($options);
        return $this->command($command);
    }
}
```

### Custom Streams

Create custom stream types:

```php
class SubtitleStream extends Stream
{
    public static function make(Media $media): self
    {
        return new self($media, 'text');
    }
}
```

### Custom Results

Extend result objects:

```php
class DetailedPackagerResult extends PackagerResult
{
    public function getDuration(): float
    {
        return $this->getMetadataValue('duration');
    }
}
```

## Best Practices

1. **Always use dependency injection** - Get `Packager` from the container
2. **Use the facade for simple operations** - `Streamer::open()` for quick tasks
3. **Use the driver directly only when needed** - For low-level control
4. **Enable logging in production** - Track packaging operations
5. **Set appropriate timeouts** - Based on your content size
6. **Handle exceptions appropriately** - Different errors need different handling
7. **Use the verification command** - During deployment: `php artisan shaka:verify`

## Performance Considerations

- **Long-running operations** - Adjust timeout based on content
- **Memory usage** - Large files may require more memory
- **Parallel processing** - Consider queuing for multiple files
- **Temporary files** - Clean up with `cleanupTemporaryFiles()`
- **Remote disks** - Files are copied locally before processing

## Security Considerations

- **Binary path validation** - Driver validates binary existence
- **Input sanitization** - Use proper escaping for file paths
- **Encryption** - Use `withEncryption()` for DRM content
- **Access control** - Validate user permissions before processing
- **Temporary files** - Ensure proper cleanup and permissions

# Configuration

Laravel Shaka can be configured via the `config/shaka.php` file.

## Publishing Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="shaka-config"
```

## Configuration Options

### Packager Binary

Configure the path to the Shaka Packager binary:

```php
'packager' => [
    'binaries' => env('PACKAGER_PATH', '/usr/local/bin/packager'),
],
```

**Environment Variable:**

```env
PACKAGER_PATH=/usr/local/bin/packager
```

**Multiple Binary Paths:**
The system will search for the first available binary:

```php
'packager' => [
    'binaries' => [
        '/usr/local/bin/packager',
        '/usr/bin/packager',
        '/opt/shaka-packager/packager',
    ],
],
```

### Timeout

Set the maximum execution time for packaging operations:

```php
'timeout' => 60 * 60 * 4, // 4 hours in seconds
```

**Environment Variable:**

```env
PACKAGER_TIMEOUT=14400
```

**Considerations:**

- Longer videos require more time
- 4K content takes significantly longer than 1080p
- Multiple quality variants multiply processing time
- Consider your server's PHP `max_execution_time` setting

### Logging

Enable logging to track packaging operations:

```php
'log_channel' => env('PACKAGER_LOG_CHANNEL', false),
```

**Environment Variables:**

```env
# Disable logging (default)
PACKAGER_LOG_CHANNEL=false

# Use default log channel
PACKAGER_LOG_CHANNEL=stack

# Use custom channel
PACKAGER_LOG_CHANNEL=packager
```

**Custom Log Channel:**
Define a custom channel in `config/logging.php`:

```php
'channels' => [
    'packager' => [
        'driver' => 'daily',
        'path' => storage_path('logs/packager.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

### Temporary Files

Configure where temporary files are stored:

```php
'temporary_files_root' => env('PACKAGER_TEMPORARY_FILES_ROOT', storage_path('app/packager/temp')),
```

**Environment Variable:**

```env
PACKAGER_TEMPORARY_FILES_ROOT=/tmp/packager
```

**Considerations:**

- Remote files (S3, etc.) are copied here before processing
- Ensure sufficient disk space
- Clean up regularly with `cleanupTemporaryFiles()`
- Use `/dev/shm` for faster processing (RAM disk)

### Encrypted Files

Configure location for encrypted temporary files:

```php
'temporary_files_encrypted' => env('PACKAGER_TEMPORARY_ENCRYPTED', '/dev/shm'),
```

**Environment Variable:**

```env
PACKAGER_TEMPORARY_ENCRYPTED=/dev/shm
```

## Complete Configuration Example

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shaka Packager Binary
    |--------------------------------------------------------------------------
    |
    | Path to the Shaka Packager binary. Can be a string or array of paths.
    | The system will use the first available binary.
    |
    */

    'packager' => [
        'binaries' => env('PACKAGER_PATH', '/usr/local/bin/packager'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum execution time in seconds for packaging operations.
    | Adjust based on your content size and quality requirements.
    |
    */

    'timeout' => env('PACKAGER_TIMEOUT', 60 * 60 * 4), // 4 hours

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Log channel for packaging operations. Set to false to disable logging.
    | Use your default log channel or define a custom one.
    |
    */

    'log_channel' => env('PACKAGER_LOG_CHANNEL', false),

    /*
    |--------------------------------------------------------------------------
    | Temporary Files
    |--------------------------------------------------------------------------
    |
    | Root directory for temporary files during packaging operations.
    | Remote files are downloaded here before processing.
    |
    */

    'temporary_files_root' => env('PACKAGER_TEMPORARY_FILES_ROOT', storage_path('app/packager/temp')),

    /*
    |--------------------------------------------------------------------------
    | Encrypted Temporary Files
    |--------------------------------------------------------------------------
    |
    | Directory for encrypted temporary files. Using /dev/shm (RAM disk)
    | provides better performance for encryption operations.
    |
    */

    'temporary_files_encrypted' => env('PACKAGER_TEMPORARY_ENCRYPTED', '/dev/shm'),

];
```

## Environment Configuration

Example `.env` configuration:

```env
# Shaka Packager Configuration
PACKAGER_PATH=/usr/local/bin/packager
PACKAGER_TIMEOUT=14400
PACKAGER_LOG_CHANNEL=packager
PACKAGER_TEMPORARY_FILES_ROOT=/tmp/packager
PACKAGER_TEMPORARY_ENCRYPTED=/dev/shm
```

## Verification

After configuration, verify your setup:

```bash
php artisan shaka:verify
```

This command checks:

- Binary path is valid and executable
- Can retrieve version information
- Timeout is configured
- Logger is properly set up

## Runtime Configuration

You can also configure the packager at runtime:

```php
use Foxws\Streamer\Support\Packager\Packager;
use Foxws\Streamer\Support\Packager\ShakaPackager;

// Create with custom configuration
$driver = new ShakaPackager(
    binaryPath: '/custom/path/packager',
    logger: Log::channel('custom'),
    timeout: 7200
);

$packager = new Packager($driver, Log::channel('custom'));
```

Or using the static create method:

```php
$packager = Packager::create(
    logger: Log::channel('packager'),
    configuration: [
        'packager' => ['binaries' => '/custom/path/packager'],
        'timeout' => 7200,
    ]
);
```

## Driver Configuration

Modify driver settings after instantiation:

```php
$driver = app(ShakaPackager::class);

// Change timeout
$driver->setTimeout(7200);

// Change logger
$driver->setLogger(Log::channel('debug'));
```

## Troubleshooting

### Binary Not Found

If you see "Executable not found" errors:

1. Verify the binary exists: `which packager`
2. Check permissions: `ls -l /usr/local/bin/packager`
3. Ensure it's executable: `chmod +x /usr/local/bin/packager`
4. Update config with correct path

### Timeout Errors

If operations timeout:

1. Increase timeout in config
2. Check server PHP `max_execution_time`
3. Consider queueing long operations
4. Optimize video settings (resolution, bitrate)

### Permission Errors

If you see permission errors:

1. Check temporary directory permissions
2. Ensure web server user can write
3. Verify binary is executable
4. Check SELinux/AppArmor policies

### Logging Issues

If logging doesn't work:

1. Verify log channel exists in `config/logging.php`
2. Check log directory permissions
3. Ensure channel is properly configured
4. Test with a simple log entry

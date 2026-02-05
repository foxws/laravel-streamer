# Configuration

Laravel Shaka Streamer can be configured via the `config/streamer.php` file.

## Publishing Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="shaka-config"
```

## Configuration Options

### Streamer Configuration

Configure the Shaka Streamer Python package and binary:

```php
'streamer' => [
    'python_binary' => env('STREAMER_PYTHON_BINARY', 'python3'),
    'streamer_binary' => env('STREAMER_BINARY', 'shaka-streamer'),
],
```

**Environment Variables:**

```env
STREAMER_PYTHON_BINARY=/usr/bin/python3.11
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
'log_channel' => env('STREAMER_LOG_CHANNEL', null),
```

**Environment Variables:**

```env
# Disable logging (default)
STREAMER_LOG_CHANNEL=null

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

## Complete Configuration Example

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Streamer Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Python binary and Shaka Streamer executable paths.
    |
    */

    'streamer' => [
        'python_binary' => env('STREAMER_PYTHON_BINARY', 'python3'),
        'streamer_binary' => env('STREAMER_BINARY', 'shaka-streamer'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Force Generic Input
    |--------------------------------------------------------------------------
    |
    | Use generic input paths instead of absolute paths.
    |
    */

    'force_generic_input' => env('STREAMER_FORCE_GENERIC_INPUT', true),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum execution time in seconds for streaming operations.
    |
    */

    'timeout' => env('STREAMER_TIMEOUT', 60 * 60 * 4),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Log channel for streaming operations. Set to false to disable logging.
    |
    */

    'log_channel' => env('STREAMER_LOG_CHANNEL', null),

    /*
    |--------------------------------------------------------------------------
    | Temporary Files
    |--------------------------------------------------------------------------
    |
    | Root directory for temporary files during streaming operations.
    |
    */

    'temporary_files_root' => env('STREAMER_TEMPORARY_FILES_ROOT', storage_path('app/streamer/temp')),

    /*
    |--------------------------------------------------------------------------
    | Cache Files Root
    |--------------------------------------------------------------------------
    |
    | Directory for cache files (encryption keys, manifests, etc.).
    |
    */

    'cache_files_root' => env('STREAMER_CACHE_FILES_ROOT', '/dev/shm'),
];
```

## Environment Configuration

Example `.env` configuration:

```env
# Shaka Streamer Configuration
STREAMER_PYTHON_BINARY=python3
STREAMER_BINARY=shaka-streamer
STREAMER_TIMEOUT=14400
STREAMER_LOG_CHANNEL=streamer
STREAMER_TEMPORARY_FILES_ROOT=/tmp/streamer
STREAMER_CACHE_FILES_ROOT=/dev/shm
STREAMER_FORCE_GENERIC_INPUT=true
```

## Verification

After configuration, verify your setup:

```bash
php artisan streamer:verify
```

This command checks:

- Python binary is available
- Shaka Streamer executable is callable
- Configuration is properly loaded
- Logger is properly set up

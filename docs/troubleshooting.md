---
sidebar_position: 9
---

# Troubleshooting

Common issues and their solutions when using Laravel Shaka Streamer.

## Shaka Streamer Issues

### Shaka Streamer Not Installed

**Error:**

```
Error: shaka-streamer binary not found
```

**Solution:**

1. Install via pip:

    ```bash
    python3 -m pip install shaka-streamer
    ```

2. Verify installation:

    ```bash
    python3 -m pip show shaka-streamer
    ```

3. Configure in `.env`:

    ```env
    STREAMER_BINARY=shaka-streamer
    ```

## Temporary Directory Issues

### Permission Denied

**Error:**

```
Permission denied: /var/www/html/storage/app/streamer/temp
```

**Solution:**

1. Create directory:

    ```bash
    mkdir -p storage/app/streamer/temp
    chmod 755 storage/app/streamer/temp
    ```

2. Set proper ownership:

    ```bash
    sudo chown -R www-data:www-data storage/app/streamer/temp
    ```

3. Or configure alternate path in `config/streamer.php`:

    ```php
    'temporary_files_root' => storage_path('app/streamer/temp'),
    ```

### No Space Left on Device

**Error:**

```
No space left on device
```

**Solution:**

1. Check disk space:

    ```bash
    df -h storage/app/streamer/temp
    ```

2. Clean up old temporary files:

    ```bash
    find storage/app/streamer/temp -mtime +7 -delete
    ```

3. Configure to use alternative disk:

    ```env
    STREAMER_TEMPORARY_FILES_ROOT=/mnt/alternate-disk/streamer-temp
    ```

### Insufficient Storage Space (pre-flight check)

**Error:**

```
InsufficientStorageException: Insufficient storage space in [/dev/shm]: 31457280 bytes free, 1073741824 bytes required.
```

Unlike "No Space Left on Device" above, this is thrown *before* streaming
starts by a deliberate pre-flight check (see [Storage Space
Guards](./configuration.md#storage-space-guards)) - nothing ran, so there's
nothing to clean up.

**Solution:**

1. If `temporary_files_root` or `cache_files_root` is a size-limited mount
   (e.g. a tmpfs), free up space or increase its size.
2. If this happens routinely under concurrent load, lower your queue's
   concurrency rather than raising the floor further - the floor is a
   safety net, not capacity planning.
3. Tune or disable the checks via `STREAMER_TEMPORARY_MIN_FREE` /
   `STREAMER_CACHE_MIN_FREE` (bytes, `0` disables).

## Timeout Issues

### Operation Timed Out

**Error:**

```
The process timed out
```

**Solution:**

1. Increase timeout in `.env`:

    ```env
    STREAMER_TIMEOUT=28800  # 8 hours
    ```

2. Check server PHP configuration:

    ```bash
    php -r "echo ini_get('max_execution_time');"
    ```

3. Adjust if necessary:

    ```php
    set_time_limit(0); // Unlimited for CLI
    ```

## Logging Issues

### Logs Not Being Written

**Error:**

```
Log channel not working
```

**Solution:**

1. Verify logging is enabled:

    ```env
    STREAMER_LOG_CHANNEL=streamer
    ```

2. Ensure channel exists in `config/logging.php`:

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

3. Check directory permissions:

    ```bash
    chmod 755 storage/logs
    ```

## General Troubleshooting

### Configuration Check

Verify configuration is correct:

```bash
php artisan streamer:info
```

### Enable Debug Logging

For more detailed information:

```env
STREAMER_LOG_CHANNEL=streamer
APP_DEBUG=true
```

### Clear Cache

Reset configuration cache:

```bash
php artisan config:clear
php artisan cache:clear
```

### Test Command Execution

Test if streamer can execute:

```php
use Foxws\Streamer\Support\ShakaStreamer;

$driver = ShakaStreamer::create();
$version = $driver->getVersion();
echo "Streamer Version: {$version}";
```

## Getting Help

If issues persist:

1. Check application logs: `storage/logs/streamer.log`
2. Review debug output: `php artisan tinker`
3. File an issue on GitHub with:
    - Complete error message
    - Configuration (without sensitive data)
    - PHP and OS versions
    - Steps to reproduce

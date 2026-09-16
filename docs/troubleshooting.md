---
section: Advanced
order: 2
---

# Troubleshooting

Common issues you might run into with this package, and how to fix them.

## Shaka Streamer issues

### Shaka Streamer not installed

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

## Temporary directory issues

### Permission denied

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

### No space left on device

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

### Insufficient storage space (pre-flight check)

**Error:**

```
InsufficientStorageException: Insufficient storage space in [/dev/shm]: 31457280 bytes free, 1073741824 bytes required.
```

Unlike "No space left on device" above, this error is thrown *before*
packaging starts, by a deliberate pre-flight check (see [Storage space
guards](./configuration.md#storage-space-guards)). Nothing ran yet, so
there's nothing to clean up.

**Solution:**

1. If `temporary_files_root` or `cache_files_root` is a size-limited mount
   (e.g. a tmpfs), free up space or make it bigger.
2. If this happens often under concurrent load, lower your queue's
   concurrency instead of raising the floor further — the floor is a safety
   net, not a capacity plan.
3. Tune or disable the checks via `STREAMER_TEMPORARY_MIN_FREE` /
   `STREAMER_CACHE_MIN_FREE` (in bytes; `0` disables the check).

## Timeout issues

### Operation timed out

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

## Logging issues

### Logs not being written

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

## General troubleshooting

### Configuration check

Check that your configuration is correct:

```bash
php artisan streamer:info
```

### Enable debug logging

For more detailed information:

```env
STREAMER_LOG_CHANNEL=streamer
APP_DEBUG=true
```

### Clear cache

Reset the configuration cache:

```bash
php artisan config:clear
php artisan cache:clear
```

### Test command execution

Check that the streamer binary can actually run:

```php
use Foxws\Streamer\Support\ShakaStreamer;

$driver = ShakaStreamer::create();
$version = $driver->getVersion();
echo "Streamer Version: {$version}";
```

## Getting help

If the problem doesn't go away:

1. Check the application logs: `storage/logs/streamer.log`
2. Review debug output with `php artisan tinker`
3. File an issue on GitHub with:
    - The complete error message
    - Your configuration (with any sensitive data removed)
    - Your PHP and OS versions
    - Steps to reproduce the problem

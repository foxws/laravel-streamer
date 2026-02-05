# Troubleshooting Guide

Common issues and their solutions when using Laravel Shaka Streamer.

## Python & Shaka Streamer Issues

### Python Binary Not Found

**Error:**
```
Error: Python 3 not found or not in PATH
```

**Solution:**

1. Install Python 3:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install python3
   
   # macOS
   brew install python3
   ```

2. Verify installation:
   ```bash
   python3 --version
   ```

3. Configure in `.env`:
   ```env
   STREAMER_PYTHON_BINARY=/usr/bin/python3.11
   ```

### Shaka Streamer Not Installed

**Error:**
```
Error: shaka-streamer module not found
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
   STREAMER_PYTHON_BINARY=python3
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
php artisan shaka:verify
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

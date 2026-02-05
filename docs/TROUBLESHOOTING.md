# Troubleshooting Guide

Common issues and their solutions when using Laravel Shaka Packager.

## Installation Issues

### Binary Not Found

**Error:**

```
ExecutableNotFoundException: Shaka Packager binary not found at: /usr/local/bin/packager
```

**Solutions:**

1. Install Shaka Packager:

    ```bash
    # Linux
    wget https://github.com/shaka-project/shaka-packager/releases/download/v3.4.2/packager-linux-x64
    sudo mv packager-linux-x64 /usr/local/bin/packager
    sudo chmod +x /usr/local/bin/packager

    # macOS
    brew install shaka-packager
    ```

2. Update config path:

    ```bash
    # .env
    PACKAGER_PATH=/path/to/packager
    ```

3. Verify installation:

    ```bash
    php artisan shaka:verify
    ```

### Binary Not Executable

**Error:**

```
Binary is not executable
```

**Solution:**

```bash
chmod +x /usr/local/bin/packager
```

## Configuration Issues

### Temporary Directory Not Writable

**Error:**

```
Temporary directory is not writable
```

**Solutions:**

1. Create directory:

    ```bash
    mkdir -p storage/app/packager/temp
    chmod 755 storage/app/packager/temp
    ```

2. Update config:

    ```php
    // config/laravel-streamer.php
    'temporary_files_root' => storage_path('app/packager/temp'),
    ```

### Timeout Errors

**Error:**

```
RuntimeException: Process timeout exceeded
```

**Solutions:**

1. Increase timeout in config:

    ```php
    // config/laravel-streamer.php
    'timeout' => 60 * 60 * 8, // 8 hours
    ```

2. Or set dynamically:

    ```php
    $packager = app(ShakaPackager::class);
    $packager->setTimeout(28800); // 8 hours
    ```

## Packaging Issues

### Unknown Field in Stream Descriptor

**Error:**

```
Unknown field in stream descriptor: filename_with,comma.mp4
```

**Solutions:**

1. Enable generic input (recommended):

    ```bash
    # .env
    PACKAGER_FORCE_GENERIC_INPUT=true
    ```

2. Or sanitize filename manually:

    ```php
    use Foxws\Streamer\Support\MediaHelper;

    $sanitized = MediaHelper::sanitizeFilename($filename);
    ```

### Empty MediaCollection

**Error:**

```
InvalidArgumentException: MediaCollection cannot be empty
```

**Solution:**

```php
// Ensure you call open() before adding streams
Shaka::open('input.mp4')  // ← Must call open first
    ->addVideoStream('input.mp4', 'output.mp4')
    ->export()
    ->save();
```

### No Streams Configured

**Error:**

```
RuntimeException: No streams configured. Use addVideoStream() or addAudioStream() first.
```

**Solution:**

```php
// Add at least one stream before exporting
Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.mp4')  // ← Add streams
    ->export()
    ->save();
```

## Encryption Issues

### SAMPLE-AES Not Working in Browser

**Problem:** Encrypted HLS doesn't play in web browsers

**Solution:** Use `cbc1` protection scheme for browser compatibility:

```php
Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'video.ts')  // Use .ts not .mp4
    ->withHlsMasterPlaylist('master.m3u8')
    ->withEncryption([
        'keys' => 'label=:key_id=abc:key=def',
        'protection_scheme' => 'cbc1',  // Browser-compatible
        'clear_lead' => 0,
    ])
    ->export()
    ->save();
```

### Encryption Key Not Found

**Error:**

```
Cannot load key from URI
```

**Solutions:**

1. Ensure key file is accessible:

    ```php
    // Make sure the key URL is publicly accessible
    ->setKeyUrlResolver(fn ($key) => Storage::disk('public')->url($key))
    ```

2. Check CORS settings for cross-origin requests

## Storage Issues

### S3 Permission Denied

**Error:**

```
S3Exception: Access Denied
```

**Solutions:**

1. Check IAM permissions:

    ```json
    {
        "Effect": "Allow",
        "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"],
        "Resource": "arn:aws:s3:::your-bucket/*"
    }
    ```

2. Verify credentials in `.env`:

    ```bash
    AWS_ACCESS_KEY_ID=your-key
    AWS_SECRET_ACCESS_KEY=your-secret
    AWS_DEFAULT_REGION=us-east-1
    AWS_BUCKET=your-bucket
    ```

### Cannot Copy Files from Temporary Directory

**Error:**

```
RuntimeException: Cannot copy files: temporary directory not set
```

**Solution:** This occurs when using `packageWithBuilder()` directly. Use the full fluent API instead:

```php
// ✗ Wrong
$builder = CommandBuilder::make()->addVideoStream(...);
$packager->packageWithBuilder($builder)->toDisk('s3');

// ✓ Correct
Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'output.mp4')
    ->export()
    ->toDisk('s3')
    ->save();
```

## Performance Issues

### Processing Too Slow

**Solutions:**

1. Use local disk for temporary files:

    ```php
    'temporary_files_root' => '/dev/shm/packager', // RAM disk
    ```

2. Reduce quality/bitrate settings
3. Use fewer ABR variants
4. Process in background queue:

    ```php
    ProcessMediaJob::dispatch($inputPath);
    ```

### Memory Issues

**Solutions:**

1. Increase PHP memory limit:

    ```ini
    memory_limit = 512M
    ```

2. Process smaller chunks
3. Use queue workers with memory limit:

    ```bash
    php artisan queue:work --memory=512
    ```

## Debugging

### Enable Logging

```bash
# .env
PACKAGER_LOG_CHANNEL=stack
```

```php
// Check logs
tail -f storage/logs/laravel.log
```

### Get Raw Command

```php
$command = Shaka::open('input.mp4')
    ->addVideoStream('input.mp4', 'output.mp4')
    ->export()
    ->getCommand();

dd($command);
```

### Test Packager Directly

```bash
/usr/local/bin/packager --version
/usr/local/bin/packager in=input.mp4,stream=video,output=output.mp4
```

## Getting Help

If you're still experiencing issues:

1. Run verification: `php artisan shaka:verify`
2. Check logs in `storage/logs/laravel.log`
3. Test packager binary directly
4. Create an issue with:
    - Error message
    - PHP version
    - Laravel version
    - Packager version
    - Relevant code snippet

## Common Pitfalls

1. **Forgetting to call `open()`** before adding streams
2. **Using wrong file extension** for encrypted content (.mp4 vs .ts)
3. **Not setting timeout** for large files
4. **Special characters in filenames** without sanitization
5. **Incorrect disk configuration** in filesystems.php
6. **Mixing input/output paths** from different contexts

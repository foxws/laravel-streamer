# AES Encryption with Different Codecs

This guide demonstrates how to use the `withAESEncryption()` method with various video codecs.

## Quick Start

```php
use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\Filesystem\MediaCollection;
use Foxws\Streamer\Support\Streamer;

// Open your media
$media = Media::make('videos', 'input.mp4');
$streamer = Streamer::create();
$streamer->open(MediaCollection::make([$media]));

// Enable encryption (uses cbc1 by default)
$encryptionKey = $streamer->withAESEncryption();

// Add your streams
$streamer->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'encrypted_video.mp4',
]);
```

`withAESEncryption()` returns an `EncryptionKey` value object with `key`, `keyId`, and `filePath` properties.

## Codec-Specific Examples

### H.264/AVC Encryption

H.264 is the most widely supported codec. Use `cbc1` for maximum compatibility:

```php
$media = Media::make('videos', 'h264_video.mp4');
$streamer->open(MediaCollection::make([$media]));

// Generate encryption key
$encryptionKey = $streamer->withAESEncryption('h264.key', 'cbc1');

// Add video stream
$streamer->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'h264_encrypted.mp4',
]);

// The key is now at: $encryptionKey->filePath
// Key: $encryptionKey->key
// Key ID: $encryptionKey->keyId
```

## Key Rotation

Automatic key rotation enhances security by periodically generating new encryption keys:

```php
$media = Media::make('videos', 'input.mp4');
$streamer->open(MediaCollection::make([$media]));

// Enable encryption with key rotation every 5 minutes
// Base name 'key' becomes: key_0.key, key_1.key, key_2.key, etc.
$encryptionKey = $streamer->withAESEncryption(); // Uses default 'key' base name
$streamer->withKeyRotationDuration(60); // 60 seconds for balanced security

$streamer->addVideoStream('input.mp4', 'video.mp4');
$streamer->withMpdOutput('manifest.mpd');
$result = $streamer->export();
```

### Common Rotation Intervals

```php
// 30 seconds - high security (Apple HLS recommendation)
->withKeyRotationDuration(30)

// 60 seconds - balanced security
->withKeyRotationDuration(60)

// 5 minutes - lower overhead, still secure
->withKeyRotationDuration(300)

// 15 minutes - minimal rotation for low-risk content
->withKeyRotationDuration(900)
```

### How It Works

Shaka Streamer's underlying Shaka Packager stage automatically:

1. Generates a new key at each rotation interval
2. Embeds key URIs in the manifest (#EXT-X-KEY tags for HLS)
3. Encrypts segments with the appropriate key based on timing

Players automatically fetch the correct key for each segment.

### Collecting Rotated Keys

After streaming with key rotation, the keys are automatically tracked when uploading:

```php
$streamer->withAESEncryption(); // Default: key_0.key, key_1.key, key_2.key...
$streamer->withKeyRotationDuration(300);
$streamer->addVideoStream('input.mp4', 'video.mp4');
$streamer->withMpdOutput('manifest.mpd');
$result = $streamer->export();

// Upload everything (segments + keys) to S3 private bucket
$result->toDisk('s3', 'videos');

// Get all keys that were uploaded - store metadata in database
$uploadedKeys = $result->getEncryptionKeys();

foreach ($uploadedKeys as $key) {
    EncryptionKey::create([
        'filename' => $key->filename,    // e.g., "key_0.key", "key_1.key"
        'path' => $key->path,            // S3 path: "videos/key_0.key"
        'key' => $key->content,          // Hex-encoded key content
        'video_id' => $video->id,
    ]);
}
```

That's it! `toDisk()` automatically uploads both segments and encryption keys to your **private S3 bucket**.

### Serving Keys with Dynamic URLs

Use `setKeyUrlResolver()` to generate signed temporary URLs dynamically when serving playlists:

```php
use Foxws\Streamer\Http\DynamicHLSPlaylist;

// In your controller
public function playlist(Video $video)
{
    $playlist = (new DynamicHLSPlaylist('s3'))
        ->setKeyUrlResolver(function ($keyFilename) use ($video) {
            // Generate signed URL on-demand (expires in 1 hour)
            return Storage::disk('s3')->temporaryUrl(
                "videos/{$video->id}/{$keyFilename}",
                now()->addHour()
            );
        })
        ->open($video->hls_master_path);

    return $playlist->toResponse(request());
}
```

**Benefits:**

- URLs are generated fresh on every request
- No need to store/track expiration times
- Keys remain in private S3 bucket
- Players fetch keys transparently

## Codec-Specific Examples (continued)

### HEVC/H.265 Encryption

HEVC offers better compression. Use `cbcs` for modern devices:

```php
$media = Media::make('videos', 'hevc_video.mp4');
$streamer->open(MediaCollection::make([$media]));

// Use cbcs for HEVC (better for newer devices)
$encryptionKey = $streamer->withAESEncryption('hevc.key', 'cbcs');

$streamer->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'hevc_encrypted.mp4',
]);
```

### AV1 Encryption

AV1 is a modern, royalty-free codec with excellent compression:

```php
$media = Media::make('videos', 'av1_video.mp4');
$streamer->open(MediaCollection::make([$media]));

// AV1 works with all protection schemes
$encryptionKey = $streamer->withAESEncryption('av1.key', 'cenc');

$streamer->addStream([
    'in' => $media->getLocalPath(),
    'stream' => 'video',
    'output' => 'av1_encrypted.mp4',
]);
```

## Protection Schemes

### cbc1 (Default - Most Compatible)

Best for HLS and maximum browser compatibility:

```php
$encryptionKey = $streamer->withAESEncryption('encryption.key', 'cbc1');
// Compatible with: Safari, Chrome, Firefox, Edge, iOS, Android
```

### cbcs (Modern Devices)

For newer platforms with better performance:

```php
$encryptionKey = $streamer->withAESEncryption('encryption.key', 'cbcs');
// Compatible with: iOS 10+, Android 7+, modern browsers
```

### cenc (Common Encryption)

DASH standard, widely supported:

```php
$encryptionKey = $streamer->withAESEncryption('encryption.key', 'cenc');
// Compatible with: Most DASH players, EME-enabled browsers
```

### SAMPLE-AES (HLS-Specific)

For HLS without a protection scheme:

```php
$encryptionKey = $streamer->withAESEncryption('hls.key', null);
// Compatible with: HLS players, Apple devices
```

Every method above also accepts a `Foxws\Streamer\Support\ProtectionScheme` enum
case (`ProtectionScheme::Cbc1`, `::Cbcs`, `::Cenc`, `::Cens`) instead of a raw
string, if you'd rather not deal with typos in the scheme name.

## Multi-Codec Packaging

Package multiple codecs with a single encryption key:

```php
$h264 = Media::make('videos', 'h264.mp4');
$hevc = Media::make('videos', 'hevc.mp4');
$av1 = Media::make('videos', 'av1.mp4');

$collection = MediaCollection::make([$h264, $hevc, $av1]);
$streamer->open($collection);

// One key for all codecs (with optional label for organization)
$encryptionKey = $streamer->withAESEncryption('master.key', 'cbc1', 'multi');

// Add streams for each codec
$streamer->addStream([
    'in' => $h264->getLocalPath(),
    'stream' => 'video',
    'output' => 'h264_1080p.mp4',
]);

$streamer->addStream([
    'in' => $hevc->getLocalPath(),
    'stream' => 'video',
    'output' => 'hevc_1080p.mp4',
]);

$streamer->addStream([
    'in' => $av1->getLocalPath(),
    'stream' => 'video',
    'output' => 'av1_1080p.mp4',
]);

// All streams will be encrypted with the same key
$result = $streamer->export();
```

## Separate Keys Per Codec

For advanced scenarios, use different keys for each codec:

```php
// H.264 with its own key
$streamerH264 = Streamer::create();
$streamerH264->open(MediaCollection::make([Media::make('videos', 'h264.mp4')]));
$keyH264 = $streamerH264->withAESEncryption('h264.key');

// HEVC with its own key
$streamerHevc = Streamer::create();
$streamerHevc->open(MediaCollection::make([Media::make('videos', 'hevc.mp4')]));
$keyHevc = $streamerHevc->withAESEncryption('hevc.key');

// AV1 with its own key
$streamerAv1 = Streamer::create();
$streamerAv1->open(MediaCollection::make([Media::make('videos', 'av1.mp4')]));
$keyAv1 = $streamerAv1->withAESEncryption('av1.key');

// Each codec has unique encryption keys
```

## HLS with Encryption

Complete HLS packaging with encryption:

```php
$media = Media::make('videos', 'video.mp4');
$streamer->open(MediaCollection::make([$media]));

// Generate encryption key
$encryptionKey = $streamer->withAESEncryption('encryption.key', 'cbc1');

// Add video variants
$streamer
    ->addVideoStream($media->getLocalPath(), 'video_1080p.mp4', ['bandwidth' => '5000000'])
    ->addVideoStream($media->getLocalPath(), 'video_720p.mp4', ['bandwidth' => '3000000'])
    ->addAudioStream($media->getLocalPath(), 'audio.mp4', ['language' => 'en'])
    ->withHlsMasterPlaylist('master.m3u8');

$result = $streamer->export();

// The encryption key will be referenced in the HLS playlist
// Player will fetch 'encryption.key' to decrypt segments
```

## DASH with Encryption

Complete DASH packaging with encryption:

```php
$media = Media::make('videos', 'video.mp4');
$streamer->open(MediaCollection::make([$media]));

// Use cenc for DASH
$encryptionKey = $streamer->withAESEncryption('encryption.key', 'cenc');

$streamer
    ->addVideoStream($media->getLocalPath(), 'video_1080p.mp4', ['bandwidth' => '5000000'])
    ->addVideoStream($media->getLocalPath(), 'video_720p.mp4', ['bandwidth' => '3000000'])
    ->addAudioStream($media->getLocalPath(), 'audio.mp4', ['language' => 'en'])
    ->withMpdOutput('manifest.mpd');

$result = $streamer->export();
```

## Key Storage

The encryption key is stored in two locations:

1. **Cache storage** (RAM disk if available): Fast temporary storage for key generation
    - Default: `/dev/shm` (Linux) or system temp directory
    - Configure via: `STREAMER_CACHE_FILES_ROOT` environment variable

2. **Export directory**: Copied to streaming output for cloud storage upload
    - Automatically included when exporting to S3 or other storage
    - Key file name is customizable via the `$keyFilename` parameter

```php
$encryptionKey = $streamer->withAESEncryption('my-custom-key.bin');

// Key is in cache: /dev/shm/random-hash/my-custom-key.bin
// Key is in export: /tmp/streamer-temp/random-hash/my-custom-key.bin
// Both contain identical key data

echo $encryptionKey->filePath; // Cache path
echo $encryptionKey->key;      // Hex-encoded 128-bit key
echo $encryptionKey->keyId;    // Hex-encoded key ID
```

### Secure Storage with Signed URLs

For production use, store keys in a **private S3 bucket** and use `setKeyUrlResolver()` to generate signed URLs dynamically:

```php
use Foxws\Streamer\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

// In your controller
public function streamVideo(Video $video)
{
    $this->authorize('view', $video);

    $playlist = (new DynamicHLSPlaylist('s3'))
        ->setKeyUrlResolver(function ($keyFilename) use ($video) {
            // Generate fresh signed URL for each key request
            return Storage::disk('s3')->temporaryUrl(
                "videos/{$video->id}/{$keyFilename}",
                now()->addHour()
            );
        })
        ->setMediaUrlResolver(function ($segmentFilename) use ($video) {
            // Also sign segment URLs for complete security
            return Storage::disk('s3')->temporaryUrl(
                "videos/{$video->id}/{$segmentFilename}",
                now()->addHours(2)
            );
        })
        ->open($video->hls_master_path);

    return $playlist->toResponse(request());
}
```

**Benefits:**

- Keys are never publicly accessible
- URLs generated fresh on each request
- No need to track expiration times
- Players fetch keys and segments transparently
- Revoke access via authorization checks

## Troubleshooting

### Codec Not Supported

Ensure your input video is actually encoded with the expected codec:

```bash
ffmpeg -i video.mp4
# Look for "Video: h264" or "Video: hevc" or "Video: av1"
```

### Protection Scheme Issues

Different devices support different protection schemes:

- **Safari/iOS**: Use `cbc1` or null (SAMPLE-AES)
- **Chrome/Android**: Use `cbc1`, `cbcs`, or `cenc`
- **DASH Players**: Use `cenc`
- **HLS Players**: Use `cbc1` or null

### Key File Not Found

Ensure the key file is copied to your export directory:

```php
// The package automatically copies the key for you
$encryptionKey = $streamer->withAESEncryption('encryption.key');

// Key is now in both cache and export temp directories
// When you export/upload, the key file will be included
```

## API Reference

```php
/**
 * Enable AES-128 encryption with auto-generated keys.
 *
 * When used with withKeyRotationDuration(), the filename becomes a base name
 * (e.g., 'key' becomes 'key_0.key', 'key_1.key', 'key_2.key', etc.).
 *
 * @param string $keyFilename Base name for key file (default: 'key')
 * @param ProtectionScheme|string|null $protectionScheme 'cbc1', 'cbcs', 'cenc', 'cens', or null for SAMPLE-AES
 * @param string|null $label Optional label for multi-key scenarios
 */
public function withAESEncryption(
    string $keyFilename = 'key',
    ProtectionScheme|string|null $protectionScheme = 'cbc1',
    ?string $label = null
): EncryptionKey

/**
 * Enable key rotation for encryption.
 *
 * @param int $seconds Duration in seconds before rotating to a new key
 * @return self
 */
public function withKeyRotationDuration(int $seconds): self
```

`EncryptionKey` is a readonly value object with three properties:

```php
final readonly class EncryptionKey
{
    public string $key;
    public string $keyId;
    public ?string $filePath;
}
```

`StreamerResult::getEncryptionKeys()` returns an array of
`Foxws\Streamer\Support\EncryptionKeyFile` value objects, each with `path`,
`filename`, and `content` (hex-encoded) properties.

## Related Documentation

- [Configuration Guide](../docs/CONFIGURATION.md)
- [Shaka Streamer Docs](https://shaka-project.github.io/shaka-streamer/)

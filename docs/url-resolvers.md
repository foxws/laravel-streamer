---
sidebar_position: 7
---

# URL Resolvers

Dynamic URL Resolvers provide a flexible way to customize how URLs are generated for your streaming content. Inspired by Laravel FFMpeg, this package provides two dedicated classes for handling HLS and DASH manifests.

## Overview

When serving adaptive streaming content (DASH/HLS), you often need to customize URLs for:

- **HLS**:
  - Encryption Keys - DRM keys for encrypted segments
  - Media Segments - `.ts` video/audio chunks
  - Playlists - `.m3u8` playlist files

- **DASH**:
  - Media Segments - Video/audio segments
  - Initialization Segments - Init segments for each representation

## Classes

### DynamicHLSPlaylist

Process and customize HLS playlists (`.m3u8` files).

```php
use Foxws\Streamer\Http\DynamicHLSPlaylist;

$playlist = new DynamicHLSPlaylist('disk-name');
```

### DynamicDASHManifest

Process and customize DASH manifests (`.mpd` files).

```php
use Foxws\Streamer\Http\DynamicDASHManifest;

$manifest = new DynamicDASHManifest('disk-name');
```

## HLS Usage

### Basic Example

```php
use Foxws\Streamer\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

$playlist = (new DynamicHLSPlaylist('videos'))
    ->setKeyUrlResolver(function ($key) {
        return route('video.key', ['key' => $key]);
    })
    ->setMediaUrlResolver(function ($filename) {
        return Storage::disk('cdn')->url($filename);
    })
    ->setPlaylistUrlResolver(function ($playlist) {
        return route('video.playlist', ['playlist' => $playlist]);
    })
    ->open('master.m3u8');

// Get processed content
$content = $playlist->get();

// Or return as HTTP response
return $playlist->toResponse($request);
```

### HLS Methods

#### `setKeyUrlResolver(callable $resolver): self`

Set resolver for encryption key URLs in `#EXT-X-KEY` tags.

```php
$playlist->setKeyUrlResolver(function (string $key) {
    return "https://keys.example.com/{$key}";
});
```

#### `setMediaUrlResolver(callable $resolver): self`

Set resolver for media segment URLs (`.ts` files).

```php
$playlist->setMediaUrlResolver(function (string $filename) {
    return "https://cdn.example.com/segments/{$filename}";
});
```

#### `setPlaylistUrlResolver(callable $resolver): self`

Set resolver for sub-playlist URLs (`.m3u8` files).

```php
$playlist->setPlaylistUrlResolver(function (string $filename) {
    return "https://example.com/playlists/{$filename}";
});
```

#### `get(): string`

Returns the processed playlist content as a string.

```php
$content = $playlist->get();
```

#### `all(): Collection`

Returns a collection of all processed playlists (master + variants).

```php
$allPlaylists = $playlist->all();

foreach ($allPlaylists as $path => $content) {
    // Process each playlist
}
```

#### `toResponse($request)`

Returns an HTTP response with correct content type (`application/vnd.apple.mpegurl`).

```php
return $playlist->toResponse($request);
```

## DASH Usage

### Basic Example

```php
use Foxws\Streamer\Http\DynamicDASHManifest;
use Illuminate\Support\Facades\Storage;

$manifest = (new DynamicDASHManifest('videos'))
    ->setMediaUrlResolver(function ($filename) {
        return Storage::disk('cdn')->url("segments/{$filename}");
    })
    ->setInitUrlResolver(function ($filename) {
        return Storage::disk('cdn')->url("init/{$filename}");
    })
    ->open('manifest.mpd');

// Get processed content
$content = $manifest->get();

// Or return as HTTP response
return $manifest->toResponse($request);
```

### DASH Methods

#### `setMediaUrlResolver(callable $resolver): self`

Set resolver for media segment URLs and `BaseURL` elements.

```php
$manifest->setMediaUrlResolver(function (string $filename) {
    return "https://cdn.example.com/media/{$filename}";
});
```

#### `setInitUrlResolver(callable $resolver): self`

Set resolver for initialization segment URLs.

```php
$manifest->setInitUrlResolver(function (string $filename) {
    return "https://cdn.example.com/init/{$filename}";
});
```

#### `get(): string`

Returns the processed manifest content as a string.

```php
$content = $manifest->get();
```

#### `toResponse($request)`

Returns an HTTP response with correct content type (`application/dash+xml`).

```php
return $manifest->toResponse($request);
```

## Performance

Both classes automatically cache resolved URLs for optimal performance. Each unique filename is only resolved once per instance.

```php
// First call - resolver is executed
$playlist->setMediaUrlResolver(fn ($file) => "https://cdn.example.com/{$file}");

// Subsequent calls for the same file use cached result
```

Cache is automatically cleared when you set a new resolver.

## Use Cases

### 1. CDN Integration

```php
$playlist = (new DynamicHLSPlaylist('videos'))
    ->setMediaUrlResolver(function ($filename) {
        return config('services.cdn.url')."/{$filename}";
    })
    ->open('master.m3u8');
```

### 2. Signed URLs for Security

```php
$playlist = (new DynamicHLSPlaylist('private'))
    ->setKeyUrlResolver(function ($key) {
        return Storage::disk('s3')->temporaryUrl("keys/{$key}", now()->addHour());
    })
    ->setMediaUrlResolver(function ($filename) {
        return Storage::disk('s3')->temporaryUrl("segments/{$filename}", now()->addHours(2));
    })
    ->open('master.m3u8');
```

### 3. Multi-tenant Applications

```php
$tenantId = auth()->user()->tenant_id;

$playlist = (new DynamicHLSPlaylist('tenants'))
    ->setMediaUrlResolver(function ($filename) use ($tenantId) {
        return route('tenant.media', ['tenant' => $tenantId, 'file' => $filename]);
    })
    ->open("tenant-{$tenantId}/master.m3u8");
```

### 4. Controller Integration

```php
namespace App\Http\Controllers;

use App\Models\Video;
use Foxws\Streamer\Http\DynamicHLSPlaylist;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function playlist(Request $request, Video $video)
    {
        $this->authorize('view', $video);

        $playlist = (new DynamicHLSPlaylist('videos'))
            ->setKeyUrlResolver(fn ($key) => route('video.key', ['video' => $video->id, 'key' => $key]))
            ->setMediaUrlResolver(fn ($file) => Storage::disk('cdn')->url("videos/{$video->id}/{$file}"))
            ->setPlaylistUrlResolver(fn ($pl) => route('video.playlist', ['video' => $video->id, 'playlist' => $pl]))
            ->open($video->hls_path);

        return $playlist->toResponse($request);
    }

    public function key(Video $video, string $key)
    {
        $this->authorize('view', $video);

        return Storage::disk('private')->download("videos/{$video->id}/keys/{$key}");
    }
}
```

### 5. DASH with Multiple CDNs

```php
$manifest = (new DynamicDASHManifest('videos'))
    ->setMediaUrlResolver(function ($filename) {
        // Route to different CDNs based on file type
        if (str_contains($filename, 'video')) {
            return "https://video-cdn.example.com/{$filename}";
        }
        return "https://audio-cdn.example.com/{$filename}";
    })
    ->open('manifest.mpd');
```

## Best Practices

1. **Use Laravel helpers** - Leverage `route()`, `url()`, and `Storage::url()` for consistency
2. **Implement authorization** - Always check user permissions when serving media
3. **Use signed URLs for sensitive content** - Implement time-limited access with `temporaryUrl()`
4. **Handle errors gracefully** - Consider what happens if a resolver fails
5. **Test your resolvers** - Unit test your URL generation logic
6. **Cache appropriately** - URL resolution is automatically cached per instance

## Examples

For comprehensive examples, see [UrlResolverExamples.php](https://github.com/foxws/laravel-streamer/blob/main/examples/UrlResolverExamples.php).

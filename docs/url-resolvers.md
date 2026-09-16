---
section: Usage
order: 2
---

# URL Resolvers

Dynamic URL resolvers let you control how URLs are generated for your streaming content when it's served. This is useful whenever segments, keys, or playlists live somewhere that needs a custom or signed URL, such as S3 or a CDN. Inspired by Laravel FFMpeg, this package provides two dedicated classes: one for HLS playlists and one for DASH manifests.

## Overview

When serving adaptive streaming content, you'll often want to customize the URLs for:

| Format | What can be customized |
| --- | --- |
| HLS | Encryption keys (DRM keys for encrypted segments), media segments (`.ts` chunks), and playlists (`.m3u8` files) |
| DASH | Media segments and initialization segments (the setup segment for each representation) |

## Classes

### DynamicHLSPlaylist

Processes and customizes HLS playlists (`.m3u8` files).

```php
use Foxws\Streamer\Http\DynamicHLSPlaylist;

$playlist = new DynamicHLSPlaylist('disk-name');
```

### DynamicDASHManifest

Processes and customizes DASH manifests (`.mpd` files).

```php
use Foxws\Streamer\Http\DynamicDASHManifest;

$manifest = new DynamicDASHManifest('disk-name');
```

## HLS usage

### Basic example

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

### HLS methods

#### `setKeyUrlResolver(callable $resolver): self`

Sets the resolver used for encryption key URLs, in `#EXT-X-KEY` tags.

```php
$playlist->setKeyUrlResolver(function (string $key) {
    return "https://keys.example.com/{$key}";
});
```

#### `setMediaUrlResolver(callable $resolver): self`

Sets the resolver used for media segment URLs (`.ts` files).

```php
$playlist->setMediaUrlResolver(function (string $filename) {
    return "https://cdn.example.com/segments/{$filename}";
});
```

#### `setPlaylistUrlResolver(callable $resolver): self`

Sets the resolver used for sub-playlist URLs (`.m3u8` files).

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

Returns a collection with every processed playlist (the master playlist plus its variants).

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

## DASH usage

### Basic example

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

### DASH methods

#### `setMediaUrlResolver(callable $resolver): self`

Sets the resolver used for media segment URLs and `BaseURL` elements.

```php
$manifest->setMediaUrlResolver(function (string $filename) {
    return "https://cdn.example.com/media/{$filename}";
});
```

#### `setInitUrlResolver(callable $resolver): self`

Sets the resolver used for initialization segment URLs.

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

Both classes cache resolved URLs automatically. Each unique filename is only resolved once per instance, so the same file won't trigger the resolver twice.

```php
// First call - resolver is executed
$playlist->setMediaUrlResolver(fn ($file) => "https://cdn.example.com/{$file}");

// Subsequent calls for the same file use cached result
```

The cache is cleared automatically whenever you set a new resolver.

## Use cases

### 1. CDN integration

```php
$playlist = (new DynamicHLSPlaylist('videos'))
    ->setMediaUrlResolver(function ($filename) {
        return config('services.cdn.url')."/{$filename}";
    })
    ->open('master.m3u8');
```

### 2. Signed URLs for security

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

### 3. Multi-tenant applications

```php
$tenantId = auth()->user()->tenant_id;

$playlist = (new DynamicHLSPlaylist('tenants'))
    ->setMediaUrlResolver(function ($filename) use ($tenantId) {
        return route('tenant.media', ['tenant' => $tenantId, 'file' => $filename]);
    })
    ->open("tenant-{$tenantId}/master.m3u8");
```

### 4. Controller integration

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

### 5. DASH with multiple CDNs

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

## Best practices

| Practice | Why |
| --- | --- |
| Use Laravel helpers | `route()`, `url()`, and `Storage::url()` keep URL generation consistent |
| Check authorization | Always verify the user is allowed to see the media before serving it |
| Sign URLs for sensitive content | Use `temporaryUrl()` for time-limited access |
| Handle resolver errors | Decide what should happen if a resolver fails |
| Test your resolvers | Unit test the URL generation logic on its own |
| Don't worry about caching it yourself | URL resolution is already cached per instance |

## Examples

For comprehensive examples, see [UrlResolverExamples.php](https://github.com/foxws/laravel-streamer/blob/main/examples/UrlResolverExamples.php).

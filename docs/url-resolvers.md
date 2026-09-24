---
section: Usage
order: 2
---

# URL Resolvers

A playlist lists its segments, keys and sub-playlists by file name. If you store streams in a private bucket, the player can't fetch those names directly. URL resolvers rewrite every name into a URL of your choice, such as a signed S3 URL, when the playlist is requested.

## HLS

```php
use Foxws\Streamer\Facades\Streamer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

public function show(Request $request, Video $video, string $playlist)
{
    $this->authorize('view', $video);

    $disk = Storage::disk('s3');

    return Streamer::dynamicHLSPlaylist('s3')
        ->setPlaylistUrlResolver(fn (string $path) => URL::temporarySignedRoute(
            'videos.playlist', now()->addHour(), [$video, $path]
        ))
        ->setMediaUrlResolver(fn (string $path) => $disk->temporaryUrl("streams/{$video->id}/{$path}", now()->addHour()))
        ->setKeyUrlResolver(fn (string $path) => $disk->temporaryUrl("streams/{$video->id}/{$path}", now()->addMinutes(10)))
        ->open("streams/{$video->id}/{$playlist}")
        ->toResponse($request);
}
```

Each resolver gets the name as it appears in the playlist, and returns the URL to put in its place:

| Resolver | Rewrites |
| --- | --- |
| `setPlaylistUrlResolver()` | Sub-playlists (`.m3u8` lines and `#EXT-X-MEDIA` URIs) |
| `setMediaUrlResolver()` | Segments (`.mp4`, `.m4s`, `.ts`, `.m4a`, `.m4v`, `.aac`, `.vtt`) and `#EXT-X-MAP` init segments |
| `setKeyUrlResolver()` | Encryption keys in `#EXT-X-KEY` |

A resolver you don't set leaves those names unchanged. Names that are already full `http://` or `https://` URLs are skipped.

The master playlist points to sub-playlists, and those point to segments. So the playlist resolver should point back to this same route, as in the example above. That way the sub-playlists are rewritten too.

Other methods:

- `get()` returns the rewritten playlist as a string.
- `all()` returns the master and every sub-playlist, rewritten, keyed by path.
- `toResponse($request)` returns it with the `application/vnd.apple.mpegurl` content type.

## DASH

```php
return Streamer::dynamicDASHManifest('s3')
    ->setInitUrlResolver(fn (string $path) => $disk->temporaryUrl("streams/{$video->id}/{$path}", now()->addHour()))
    ->setMediaUrlResolver(fn (string $path) => $disk->temporaryUrl("streams/{$video->id}/{$path}", now()->addHour()))
    ->open("streams/{$video->id}/index.mpd")
    ->toResponse($request);
```

| Resolver | Rewrites |
| --- | --- |
| `setInitUrlResolver()` | `initialization` and `sourceURL` attributes |
| `setMediaUrlResolver()` | `media` attributes and `<BaseURL>` elements |

A signed URL is different for every file, so a `SegmentTemplate` with `$Number$` can't be signed as a whole. The manifest class expands such templates into a list of segments, so each one gets its own URL.

DASH has no key URLs. With the raw keys this package creates, the player gets the key some other way, such as a ClearKey license. See [Encryption](aes-encryption.md).

## Caching

Each name is resolved once per instance. If a playlist repeats a name, the resolver doesn't run again. Setting a new resolver clears its cache.

The response itself isn't cached. Signed URLs expire, so add caching headers that fit your URL lifetimes:

```php
$response->headers->set('Cache-Control', 'private, max-age=300');
```

## Tips

- Check that the user may view the video before building the playlist.
- Give key URLs a shorter lifetime than segment URLs.
- The route that serves the playlist should also be signed or protected, or anyone with a link can request fresh URLs.
- Browsers load segments straight from S3, so the bucket needs a CORS policy that allows your site.

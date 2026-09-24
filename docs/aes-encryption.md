---
section: Advanced
order: 1
---

# Encryption

`withAESEncryption()` generates a random 128-bit key, tells Shaka Streamer to encrypt every stream with it, and writes the key to a file that's uploaded with the segments.

```php
use Foxws\Streamer\Facades\Streamer;
use Foxws\Streamer\Support\ProtectionScheme;

$streamer = Streamer::fromDisk('media')
    ->open('videos/clip.mp4')
    ->addVideoStream('videos/clip.mp4', 'video.mp4')
    ->addAudioStream('videos/clip.mp4', 'audio.mp4')
    ->withMpdOutput('index.mpd')
    ->withHlsMasterPlaylist('master.m3u8');

$key = $streamer->withAESEncryption('key', ProtectionScheme::Cbcs);

$streamer->export()->toDisk('s3')->toPath('streams/clip/')->save();

$video->update([
    'encryption_key' => $key->key,       // 32 hex characters
    'encryption_key_id' => $key->keyId,  // 32 hex characters
]);
```

`withAESEncryption()` returns the key, not the streamer, so it can't sit in the middle of a chain. Call it on its own line.

## Arguments

```php
withAESEncryption(string $keyFilename = 'key', ProtectionScheme|string|null $protectionScheme = null, ?string $label = null): EncryptionKey
```

- `$keyFilename` is the name of the key file that's uploaded with the segments.
- `$protectionScheme` is `cenc` or `cbcs`, as a string or a `ProtectionScheme` case. Leave it `null` to use Shaka Streamer's default, `cenc`. Shaka Streamer doesn't accept `cbc1` or `cens`.
- `$label` is the key label. You only need it when streams use different keys.

The returned `EncryptionKey` has `key` and `keyId` (both hex) and `filePath`, the local path of the key file.

The first seconds of a video are encrypted too: the package sets `clear_lead` to `0`, where Shaka Streamer's default is 10.

## Choosing a protection scheme

| Scheme | Plays on |
| --- | --- |
| `cbcs` | Safari and Apple devices, and recent Chrome, Firefox and Edge. The best choice when you serve both HLS and DASH. |
| `cenc` | Chrome, Firefox, Edge and Android. Not Safari's native HLS player. |

## The HLS key URI

The package doesn't set Shaka Streamer's `hls_key_uri` yet, so the key URI in the HLS playlist is chosen by Shaka Packager and doesn't point at the uploaded key file. Set it yourself after `withAESEncryption()`:

```php
$key = $streamer->withAESEncryption('key', ProtectionScheme::Cbcs);

$streamer->withEncryption([
    ...$streamer->getBuilder()->getOptions()->get('encryption'),
    'hls_key_uri' => 'key',
]);
```

The playlist then points at `key`, which the [dynamic playlist](url-resolvers.md) can replace with a signed URL through `setKeyUrlResolver()`.

## Where the key goes

The key file is written to `cache_files_root` (by default `/dev/shm`, a RAM disk), not next to the segments. `save()` uploads it to the same folder as the segments, then deletes the local copy.

The key file is as sensitive as the video. Keep the bucket private, and only hand out key URLs to users who may watch:

- **HLS:** sign the key URL with `setKeyUrlResolver()`, or point `hls_key_uri` at your own route that checks access and returns the key.
- **DASH:** there's no key URL. Give the player the key yourself. In Shaka Player that's a ClearKey setting:

```js
player.configure({
    drm: {
        clearKeys: { [keyId]: key },
    },
});
```

Store `$key->key` and `$key->keyId` so you can serve the key later without reading the file.

## Key rotation

Shaka Streamer has no key rotation setting. `withKeyRotationDuration()` adds a field Shaka Streamer doesn't know, and the job fails with `Invalid Shaka Streamer configuration`. Don't use it. If you need key rotation, package with [Laravel Shaka](https://github.com/foxws/laravel-shaka) instead.

## Full control

To use your own key, or Widevine, skip `withAESEncryption()` and pass Shaka Streamer's [encryption config](https://shaka-project.github.io/shaka-streamer/configuration_fields.html) directly:

```php
->withEncryption([
    'enable' => true,
    'encryption_mode' => 'raw',
    'protection_scheme' => 'cbcs',
    'clear_lead' => 0,
    'hls_key_uri' => 'https://example.com/keys/clip',
    'keys' => [
        ['key_id' => $keyId, 'key' => $key],
    ],
])
```

In that case you write and store the key yourself.

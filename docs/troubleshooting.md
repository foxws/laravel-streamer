---
section: Advanced
order: 2
---

# Troubleshooting

Start with `php artisan streamer:info`. It shows whether Shaka Streamer runs and where temporary files go.

## Shaka Streamer is not installed

```text
RuntimeException: Shaka Streamer is not installed or not accessible.
```

`which shaka-streamer` found nothing. Install it with `pip install shaka-streamer`, or set `STREAMER_BINARY` to its full path, for example inside a Python virtualenv.

## Invalid Shaka Streamer configuration

Shaka Streamer rejected the config. It stops on any field it doesn't know. Common causes:

- `withKeyRotationDuration()` was called. Shaka Streamer has no key rotation setting.
- `STREAMER_EXTRA_INPUT_ARGS` is set. Leave it empty.
- A protection scheme other than `cenc` or `cbcs`.
- A typo in `withOption()` or `streamer_options`.

Check the config with `->getCommand()`, and compare it with the [configuration fields](https://shaka-project.github.io/shaka-streamer/configuration_fields.html).

## FFmpeg or Shaka Packager fails

The error output names the tool that failed.

- **`ffmpeg` or `packager` not found:** install `shaka-streamer-binaries`, or install both tools yourself and call `useSystemBinaries()`.
- **An unknown encoder, such as `h264_vaapi`:** your FFmpeg build doesn't support that hardware encoder, or `hwaccel_api` doesn't match your hardware. Try without the `hw:` prefix first.
- **Input file not found:** the path wasn't opened with `open()`, or the file is gone.

## The job times out

`Illuminate\Process\Exceptions\ProcessTimedOutException` means Shaka Streamer ran longer than `STREAMER_TIMEOUT` (default 4 hours). Encode fewer qualities, use hardware encoding, or raise the timeout. Check the job and queue timeouts too. See [Queues](queue-integration.md).

## Not enough space

```text
InsufficientStorageException: Insufficient storage space in [/cache/temp/streamer]: 314572800 bytes free, 1073741824 bytes required.
```

A [storage floor](configuration.md) stopped the job before it started, so nothing needs cleaning up. Free up space, give the mount more room, or run fewer jobs at once.

A `No space left on device` error from FFmpeg means the disk filled up during the job. Set `STREAMER_TEMPORARY_MIN_FREE` to the largest output you expect.

## Files failed to copy

```text
RuntimeException: 2 file(s) failed to copy to disk "s3": ...
```

The upload failed for the files listed. Common causes:

- Wrong S3 credentials, bucket or endpoint in `config/filesystems.php`.
- `withVisibility('public')` on a bucket that blocks public ACLs. Leave visibility unset, or allow ACLs.
- A self-hosted S3 store that needs `use_path_style_endpoint`.

## Temporary files pile up

Something isn't calling `cleanupTemporaryFiles()` after a failure. Call it in `finally` in every job. See [Usage](usage.md).

## The player won't play the stream

- **Nothing loads in the browser:** the bucket needs a CORS policy that allows your site.
- **It stops after a while:** signed URLs in the playlist expired. Give segment URLs a longer lifetime, or reload the playlist.
- **Encrypted HLS doesn't play:** set `hls_key_uri`, and serve the key. See [Encryption](aes-encryption.md).
- **Encrypted video doesn't play in Safari:** use the `cbcs` protection scheme.

## Logs

Shaka Streamer's config, with keys redacted, and its output are logged to `STREAMER_LOG_CHANNEL`. Use a separate channel to keep them apart:

```env
STREAMER_LOG_CHANNEL=streamer
```

## Still stuck?

Open an [issue](https://github.com/foxws/laravel-streamer/issues) with the output of `php artisan streamer:info`, the config from `getCommand()`, and the error message.

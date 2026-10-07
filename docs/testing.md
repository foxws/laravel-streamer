---
section: Usage
order: 2
---

# Testing

Don't run Shaka Streamer in tests. Fake laravel-media with `Media::fake()`, and let `FakeStreamer` answer Shaka Streamer:

```php
use Foxws\Media\Facades\Media;
use Foxws\Streamer\StreamerExecutable;
use Foxws\Streamer\Testing\FakeStreamer;
use Illuminate\Support\Facades\Storage;

it('encodes uploads into streams', function () {
    Storage::fake('media');

    $fake = FakeStreamer::respond(Media::fake());

    StreamVideo::dispatchSync($video);

    $fake->assertRan(StreamerExecutable::ShakaStreamer);
    Storage::disk('media')->assertExists("streams/{$video->id}/master.m3u8");
});
```

`FakeStreamer` writes the manifests of the pipeline config and a placeholder file per resolution (`video_720p.mp4`), so `save()` uploads them to the target disk like a real run.

To check what Shaka Streamer would be asked to do, read the configs from the builder:

```php
$config = Media::fromDisk('media')->open('clip.mp4')->streamer()->addStreamsFrom()->withHlsPlaylist()->config();

expect($config['pipeline']['resolutions'])->toBe(['720p', '480p']);
```

To test failures, make the next run fail:

```php
$fake->failNext(StreamerExecutable::ShakaStreamer, 'RuntimeError: ffmpeg failed');
```

laravel-media's other assertions work too, such as `assertNotRan()`, `assertRanTimes()` and `assertSaved()`.

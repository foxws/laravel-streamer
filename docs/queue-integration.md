---
section: Usage
order: 3
---

# Queues

Encoding takes a long time, often longer than the video itself. Always run it in a queued job.

## A streaming job

```php
namespace App\Jobs;

use App\Models\Video;
use Foxws\Streamer\Facades\Streamer;
use Foxws\Streamer\Support\VideoResolution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class StreamVideo implements ShouldQueue
{
    use Queueable;

    public $timeout = 14400;

    public $tries = 1;

    public function __construct(public Video $video) {}

    public function handle(): void
    {
        $streamer = Streamer::fromDisk('media')->open($this->video->path);

        try {
            $streamer
                ->addVideoStream($this->video->path, 'video.mp4')
                ->addAudioStream($this->video->path, 'audio.mp4')
                ->withResolutions(VideoResolution::make($this->video->height)->toArray())
                ->withMpdOutput('index.mpd')
                ->withHlsMasterPlaylist('master.m3u8')
                ->export()
                ->toDisk('s3')
                ->toPath("streams/{$this->video->id}/")
                ->afterSaving(fn () => $this->video->markAsReady())
                ->save();
        } finally {
            $streamer->cleanupTemporaryFiles();
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->video->markAsFailed();
    }
}
```

```php
StreamVideo::dispatch($video)->onQueue('encoding');
```

A retry starts the encode again from the beginning, so keep `$tries` low.

## Timeouts

Three timeouts need to line up:

1. `STREAMER_TIMEOUT` stops the Shaka Streamer process. The default is 14400 seconds (4 hours).
2. The job's `$timeout` stops the worker. Keep it at or above `STREAMER_TIMEOUT`, or the worker is killed while Shaka Streamer still runs.
3. The queue connection's `retry_after` must be **longer** than the job's `$timeout`. Otherwise another worker picks up the same job while the first one is still encoding.

```php
// config/queue.php
'encoding' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'encoding',
    'retry_after' => 14460,
],
```

## How many at once

FFmpeg already uses every CPU core for one encode. Running several jobs at once rarely finishes the queue faster, and it needs room in `temporary_files_root` for every job. With hardware encoding, the GPU limits how many encodes run well at once. Start with one worker:

```php
// config/horizon.php
'supervisor-encoding' => [
    'connection' => 'encoding',
    'queue' => ['encoding'],
    'maxProcesses' => 1,
    'timeout' => 14400,
    'tries' => 1,
],
```

If `temporary_files_root` is a size-limited mount, set a [storage floor](configuration.md). A job then fails right away instead of halfway through.

## Long-running workers

Queue workers live for many jobs, so:

- Always call `cleanupTemporaryFiles()` in `finally`. A failed job otherwise leaves its files behind.
- Call `useSystemBinaries()` on every job that needs it. It only applies to that job.
- Don't change the shared driver with `app(ShakaStreamer::class)->setTimeout()` inside a job. The driver is a singleton, so the change sticks for every later job in that worker.
- Use `WithoutOverlapping` or `ShouldBeUnique` if the same video can be queued twice.

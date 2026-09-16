---
section: Usage
order: 3
---

# Queue Integration

Packaging media takes time, so it's usually best done in the background rather than during a web request. This guide shows how to run this package's work through Laravel's queue system.

## A basic queue job

Here's a job that handles media packaging:

```php
<?php

namespace App\Jobs;

use Foxws\Streamer\Facades\Streamer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PackageMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $inputPath,
        public string $outputPath,
        public string $disk = 's3'
    ) {}

    public function handle(): void
    {
        Streamer::fromDisk($this->disk)
            ->open($this->inputPath)
            ->addVideoStream($this->inputPath, 'video_1080p.mp4', ['bandwidth' => '5000000'])
            ->addVideoStream($this->inputPath, 'video_720p.mp4', ['bandwidth' => '3000000'])
            ->addAudioStream($this->inputPath, 'audio.mp4')
            ->withHlsMasterPlaylist('master.m3u8')
            ->export()
            ->toPath($this->outputPath)
            ->save();
    }
}
```

## Dispatching the job

```php
use App\Jobs\PackageMediaJob;

// Dispatch to default queue
PackageMediaJob::dispatch('videos/input.mp4', 'processed/');

// Dispatch to specific queue
PackageMediaJob::dispatch('videos/input.mp4', 'processed/')
    ->onQueue('media-processing');

// Dispatch with delay
PackageMediaJob::dispatch('videos/input.mp4', 'processed/')
    ->delay(now()->addMinutes(5));
```

## A job with progress tracking

```php
<?php

namespace App\Jobs;

use Foxws\Streamer\Facades\Streamer;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PackageMediaWithProgressJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2 hours
    public int $tries = 3;

    public function __construct(
        public string $inputPath,
        public string $outputPath,
        public ?int $userId = null
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            Streamer::fromDisk('s3')
                ->open($this->inputPath)
                ->addVideoStream($this->inputPath, 'video.mp4')
                ->addAudioStream($this->inputPath, 'audio.mp4')
                ->withHlsMasterPlaylist('master.m3u8')
                ->export()
                ->afterSaving(function ($exporter, $result) {
                    // Notify user of completion
                    if ($this->userId) {
                        // Send notification
                    }
                })
                ->toPath($this->outputPath)
                ->save();
        } catch (\Exception $e) {
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Handle job failure
        \Log::error('Media packaging failed', [
            'input' => $this->inputPath,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

## Batch processing

To process several files together as one batch:

```php
use App\Jobs\PackageMediaJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$jobs = [];

foreach ($mediaFiles as $file) {
    $jobs[] = new PackageMediaJob($file, 'processed/');
}

$batch = Bus::batch($jobs)
    ->name('Media Packaging Batch')
    ->then(function (Batch $batch) {
        // All jobs completed successfully
    })
    ->catch(function (Batch $batch, Throwable $e) {
        // First batch job failure
    })
    ->finally(function (Batch $batch) {
        // The batch has finished executing
    })
    ->dispatch();
```

## Configuration recommendations

### Queue configuration

Add a dedicated connection in `config/queue.php`:

```php
'connections' => [
    'media-processing' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'media',
        'retry_after' => 7200, // 2 hours
        'block_for' => null,
    ],
],
```

### Horizon configuration (optional)

If you use Laravel Horizon, add this to `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'media-processing' => [
            'connection' => 'redis',
            'queue' => ['media'],
            'balance' => 'auto',
            'maxProcesses' => 2, // Limit concurrent packaging
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 3,
            'timeout' => 7200,
        ],
    ],
],
```

## Best practices

| Practice | Why |
| --- | --- |
| Set realistic timeouts | Packaging can take a long time, especially for longer or higher-resolution videos |
| Limit concurrent jobs | Packaging is resource-intensive, so too many at once can overload the server |
| Monitor memory | Set memory limits to avoid running out of resources |
| Implement retries | Remote storage can have transient network issues worth retrying |
| Chain cleanup jobs | Run cleanup after packaging finishes, as part of the same chain |
| Track progress | Use events or database updates so users can see how far along a job is |
| Clean up temporary files | Do this on both success and failure, not just on success |

## Example with cleanup

```php
public function handle(): void
{
    try {
        Streamer::fromDisk('s3')
            ->open($this->inputPath)
            ->addVideoStream($this->inputPath, 'video.mp4')
            ->withHlsMasterPlaylist('master.m3u8')
            ->export()
            ->toPath($this->outputPath)
            ->save();

        // Clean up temporary files
        Streamer::cleanupTemporaryFiles();
    } catch (\Exception $e) {
        // Clean up on error too
        Streamer::cleanupTemporaryFiles();
        throw $e;
    }
}
```

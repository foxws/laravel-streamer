<?php

declare(strict_types=1);

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Foxws\Streamer\Filesystem\Disk;
use Foxws\Streamer\Filesystem\TemporaryDirectories;
use Foxws\Streamer\Support\StreamerResult;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Storage::fake() always swaps the disk for a *local* fake, so it can never
 * exercise StreamerResult's async S3 upload path (isS3Disk() is false for a
 * fake disk). These tests build a real S3-driver disk backed by an
 * Aws\MockHandler instead, so the concurrent-upload code path that
 * `concurrency_workers` actually controls gets real coverage.
 *
 * @param  array<int, string>  $capturedKeys  Filled with each uploaded object's S3 key, in order.
 */
function makeMockS3Disk(array &$capturedKeys = []): Filesystem
{
    $mockHandler = new MockHandler;

    // One canned response per file the test may upload.
    for ($i = 0; $i < 10; $i++) {
        $mockHandler->append(function (CommandInterface $command) use (&$capturedKeys) {
            $capturedKeys[] = (string) $command['Key'];

            return new Result([]);
        });
    }

    config(['filesystems.disks.mock-s3' => [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'test-bucket',
        'root' => 'segments',
        'handler' => $mockHandler,
    ]]);

    return Storage::disk('mock-s3');
}

it('recognizes a real S3-driver disk as an S3 disk, unlike Storage::fake()', function () {
    $disk = Disk::make(makeMockS3Disk());

    expect($disk->isS3Disk())->toBeTrue()
        ->and(Disk::make(Storage::fake('s3'))->isS3Disk())->toBeFalse();
});

it('prefixes S3 keys with the disk root', function () {
    $disk = Disk::make(makeMockS3Disk());

    expect($disk->prefixS3Path('video.mp4'))->toBe('segments/video.mp4')
        ->and($disk->prefixS3Path('audio.mp4'))->toBe('segments/audio.mp4');
});

it('uploads files concurrently to an S3-compatible disk via async promises', function () {
    $tempDirs = new TemporaryDirectories(sys_get_temp_dir().'/test-temp');
    app()->instance(TemporaryDirectories::class, $tempDirs);

    $captured = [];
    $filesystem = makeMockS3Disk($captured);

    $tempDir = sys_get_temp_dir().'/test-s3-async-'.bin2hex(random_bytes(4));
    mkdir($tempDir, 0777, true);
    file_put_contents($tempDir.'/video.mp4', 'video data');
    file_put_contents($tempDir.'/audio.mp4', 'audio data');

    $result = new StreamerResult('success', null, $tempDir, null, ['concurrency_workers' => 5]);

    $result->toDisk($filesystem, null, false);

    expect($result->hasCopyFailures())->toBeFalse()
        ->and($captured)->toHaveCount(2)
        ->and($captured)->toContain('segments/video.mp4', 'segments/audio.mp4');

    array_map('unlink', glob($tempDir.'/*'));
    rmdir($tempDir);
    $tempDirs->deleteAll();
});

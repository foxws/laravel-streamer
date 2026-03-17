<?php

declare(strict_types=1);

namespace Foxws\Streamer\Examples;

use Foxws\Streamer\Exceptions\RuntimeException;

/**
 * Examples demonstrating fromDisk usage with the fluent API
 */
class FromDiskExamples
{
    /**
     * Example 1: Basic fromDisk usage
     */
    public function basicFromDisk(): void
    {
        // Package video from a specific disk
        $result = Streamer::fromDisk('s3')
            ->open('videos/input.mp4')
            ->addVideoStream('videos/input.mp4', 'video.mp4')
            ->addAudioStream('videos/input.mp4', 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->export();
    }

    /**
     * Example 2: Using openFromDisk helper
     */
    public function openFromDiskHelper(): void
    {
        // Combine fromDisk and open in one method
        $result = Streamer::openFromDisk('s3', 'videos/input.mp4')
            ->addVideoStream('videos/input.mp4', 'video_1080p.mp4', ['bandwidth' => '5000000'])
            ->addVideoStream('videos/input.mp4', 'video_720p.mp4', ['bandwidth' => '3000000'])
            ->addAudioStream('videos/input.mp4', 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(6)
            ->export();
    }

    /**
     * Example 3: Multiple files from different disks
     */
    public function multipleDisks(): void
    {
        // Process video from S3
        $result1 = Streamer::fromDisk('s3')
            ->open('videos/video1.mp4')
            ->addVideoStream('videos/video1.mp4', 'output1/video.mp4')
            ->withMpdOutput('output1/manifest.mpd')
            ->export();

        // Process video from local disk
        $result2 = Streamer::fromDisk('local')
            ->open('videos/video2.mp4')
            ->addVideoStream('videos/video2.mp4', 'output2/video.mp4')
            ->withMpdOutput('output2/manifest.mpd')
            ->export();
    }

    /**
     * Example 4: Private S3 bucket with adaptive bitrate
     */
    public function privateS3Bucket(): void
    {
        $result = Streamer::fromDisk('s3-private')
            ->open('private-videos/source.mp4')
            ->addVideoStream('private-videos/source.mp4', 'video_4k.mp4', [
                'bandwidth' => '10000000',
                'resolution' => '3840x2160',
            ])
            ->addVideoStream('private-videos/source.mp4', 'video_1080p.mp4', [
                'bandwidth' => '5000000',
                'resolution' => '1920x1080',
            ])
            ->addVideoStream('private-videos/source.mp4', 'video_720p.mp4', [
                'bandwidth' => '3000000',
                'resolution' => '1280x720',
            ])
            ->addAudioStream('private-videos/source.mp4', 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(4)
            ->export();
    }

    /**
     * Example 5: Processing multiple files from S3
     */
    public function multipleFilesFromS3(): void
    {
        $result = Streamer::fromDisk('s3')
            ->open([
                'videos/intro.mp4',
                'videos/main.mp4',
                'videos/outro.mp4',
            ])
            ->addVideoStream('videos/intro.mp4', 'intro_video.mp4')
            ->addAudioStream('videos/intro.mp4', 'intro_audio.mp4')
            ->addVideoStream('videos/main.mp4', 'main_video.mp4')
            ->addAudioStream('videos/main.mp4', 'main_audio.mp4')
            ->addVideoStream('videos/outro.mp4', 'outro_video.mp4')
            ->addAudioStream('videos/outro.mp4', 'outro_audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->export();
    }

    /**
     * Example 6: Switching between disks
     */
    public function switchingDisks(): void
    {
        // Start with default disk
        $shaka = Streamer::open('local-video.mp4');

        // Switch to S3 disk
        $result = $shaka->fromDisk('s3')
            ->open('s3-video.mp4')
            ->addVideoStream('s3-video.mp4', 'video.mp4')
            ->withMpdOutput('manifest.mpd')
            ->export();
    }

    /**
     * Example 7: Custom filesystem configuration
     */
    public function customFilesystem(): void
    {
        // Use a custom configured filesystem
        $result = Streamer::fromDisk('videos-archive')
            ->open('2024/december/event.mp4')
            ->addVideoStream('2024/december/event.mp4', 'video_high.mp4', [
                'bandwidth' => '8000000',
            ])
            ->addVideoStream('2024/december/event.mp4', 'video_medium.mp4', [
                'bandwidth' => '4000000',
            ])
            ->addVideoStream('2024/december/event.mp4', 'video_low.mp4', [
                'bandwidth' => '2000000',
            ])
            ->addAudioStream('2024/december/event.mp4', 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(6)
            ->export();
    }

    /**
     * Example 8: HLS with encryption from S3
     */
    public function hlsEncryptionFromS3(): void
    {
        $result = Streamer::fromDisk('s3')
            ->open('secure-content/video.mp4')
            ->addVideoStream('secure-content/video.mp4', 'video.m3u8')
            ->addAudioStream('secure-content/video.mp4', 'audio.m3u8')
            ->withHlsMasterPlaylist('master.m3u8')
            ->withEncryption([
                'keys' => 'label=:key_id=abcdef0123456789abcdef0123456789:key=0123456789abcdef0123456789abcdef',
                'key_server_url' => 'https://example.com/license',
            ])
            ->withSegmentDuration(6)
            ->export();
    }

    /**
     * Example 9: Batch processing from different disks
     */
    public function batchProcessing(): void
    {
        $videos = [
            ['disk' => 's3', 'path' => 'videos/video1.mp4'],
            ['disk' => 's3', 'path' => 'videos/video2.mp4'],
            ['disk' => 'local', 'path' => 'videos/video3.mp4'],
        ];

        foreach ($videos as $index => $video) {
            $result = Streamer::fromDisk($video['disk'])
                ->open($video['path'])
                ->addVideoStream($video['path'], "output_{$index}/video.mp4")
                ->addAudioStream($video['path'], "output_{$index}/audio.mp4")
                ->withMpdOutput("output_{$index}/manifest.mpd")
                ->export();

            logger()->info("Processed video {$index}", [
                'disk' => $video['disk'],
                'path' => $video['path'],
            ]);
        }
    }

    /**
     * Example 10: Error handling with fromDisk
     */
    public function errorHandlingWithFromDisk(): void
    {
        try {
            $result = Streamer::fromDisk('s3')
                ->open('videos/input.mp4')
                ->addVideoStream('videos/input.mp4', 'video.mp4')
                ->addAudioStream('videos/input.mp4', 'audio.mp4')
                ->withMpdOutput('manifest.mpd')
                ->export();

            logger()->info('Packaging successful from S3', $result->toArray());
        } catch (RuntimeException $e) {
            logger()->error('Packaging failed', ['error' => $e->getMessage()]);
        } catch (\InvalidArgumentException $e) {
            logger()->error('Invalid disk or path', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Example 11: Chaining operations with disk context
     */
    public function chainingWithDiskContext(): void
    {
        // Process the same file with different quality settings
        $baseDisk = 's3';
        $basePath = 'videos/source.mp4';

        // High quality for premium users
        $premiumResult = Streamer::fromDisk($baseDisk)
            ->open($basePath)
            ->addVideoStream($basePath, 'premium/video_4k.mp4', ['bandwidth' => '10000000'])
            ->addVideoStream($basePath, 'premium/video_1080p.mp4', ['bandwidth' => '5000000'])
            ->addAudioStream($basePath, 'premium/audio_high.mp4', ['bitrate' => '256000'])
            ->withMpdOutput('premium/manifest.mpd')
            ->export();

        // Standard quality for regular users
        $standardResult = Streamer::fromDisk($baseDisk)
            ->open($basePath)
            ->addVideoStream($basePath, 'standard/video_720p.mp4', ['bandwidth' => '3000000'])
            ->addVideoStream($basePath, 'standard/video_480p.mp4', ['bandwidth' => '1500000'])
            ->addAudioStream($basePath, 'standard/audio.mp4', ['bitrate' => '128000'])
            ->withMpdOutput('standard/manifest.mpd')
            ->export();
    }

    /**
     * Example 12: Getting disk information
     */
    public function getDiskInformation(): void
    {
        $shaka = Streamer::fromDisk('s3')->open('videos/input.mp4');

        // Access disk information
        $disk = $shaka->getDisk();

        logger()->info('Processing from disk', [
            'disk_name' => $disk->getName(),
            'is_local' => $disk->isLocalDisk(),
        ]);

        $result = $shaka
            ->addVideoStream('videos/input.mp4', 'video.mp4')
            ->withMpdOutput('manifest.mpd')
            ->export();
    }
}

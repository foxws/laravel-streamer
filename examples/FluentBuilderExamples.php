<?php

declare(strict_types=1);

namespace Foxws\Streamer\Examples;

use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\Filesystem\MediaCollection;
use Foxws\Streamer\Support\Packager;

/**
 * Examples using the fluent CommandBuilder API
 */
class FluentBuilderExamples
{
    /**
     * Example 1: Simple fluent API
     */
    public function simpleFluentApi(): void
    {
        $result = Streamer::open('input.mp4')
            ->addVideoStream('input.mp4', 'video.mp4')
            ->addAudioStream('input.mp4', 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->export();
    }

    /**
     * Example 2: Adaptive bitrate streaming
     */
    public function adaptiveBitrateStreaming(): void
    {
        $result = Streamer::open('input.mp4')
            ->addVideoStream('input.mp4', 'video_1080p.mp4', [
                'bandwidth' => '5000000',
            ])
            ->addVideoStream('input.mp4', 'video_720p.mp4', [
                'bandwidth' => '3000000',
            ])
            ->addVideoStream('input.mp4', 'video_480p.mp4', [
                'bandwidth' => '1500000',
            ])
            ->addAudioStream('input.mp4', 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(6)
            ->export();
    }

    /**
     * Example 3: HLS with encryption
     */
    public function hlsWithEncryption(): void
    {
        $result = Streamer::open('input.mp4')
            ->addVideoStream('input.mp4', 'video.m3u8')
            ->addAudioStream('input.mp4', 'audio.m3u8')
            ->withHlsMasterPlaylist('master.m3u8')
            ->withEncryption([
                'keys' => 'label=:key_id=abcdef0123456789abcdef0123456789:key=0123456789abcdef0123456789abcdef',
                'key_server_url' => 'https://example.com/license',
            ])
            ->withSegmentDuration(6)
            ->export();
    }

    /**
     * Example 4: Multiple input files
     */
    public function multipleInputFiles(): void
    {
        $result = Streamer::open(['input1.mp4', 'input2.mp4', 'input3.mp4'])
            ->addVideoStream('input1.mp4', 'video_1.mp4')
            ->addAudioStream('input1.mp4', 'audio_1.mp4')
            ->addVideoStream('input2.mp4', 'video_2.mp4')
            ->addAudioStream('input2.mp4', 'audio_2.mp4')
            ->addVideoStream('input3.mp4', 'video_3.mp4')
            ->addAudioStream('input3.mp4', 'audio_3.mp4')
            ->withMpdOutput('manifest.mpd')
            ->export();
    }

    /**
     * Example 5: Using Packager directly with MediaCollection
     */
    public function usingPackagerDirectly(Packager $packager): void
    {
        $mediaCollection = MediaCollection::make([
            Media::make('videos', 'input.mp4'),
        ]);

        $result = $packager
            ->open($mediaCollection)
            ->addVideoStream('input.mp4', 'video.mp4', ['bandwidth' => '5000000'])
            ->addAudioStream('input.mp4', 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(4)
            ->export();
    }

    /**
     * Example 6: Building complex streams with custom options
     */
    public function complexStreamConfiguration(): void
    {
        $result = Streamer::open('input.mp4')
            ->addStream([
                'in' => 'input.mp4',
                'stream' => 'video',
                'output' => 'video_4k.mp4',
                'bandwidth' => '10000000',
                'resolution' => '3840x2160',
            ])
            ->addStream([
                'in' => 'input.mp4',
                'stream' => 'video',
                'output' => 'video_1080p.mp4',
                'bandwidth' => '5000000',
                'resolution' => '1920x1080',
            ])
            ->addStream([
                'in' => 'input.mp4',
                'stream' => 'audio',
                'output' => 'audio_en.mp4',
                'language' => 'en',
            ])
            ->addStream([
                'in' => 'input.mp4',
                'stream' => 'audio',
                'output' => 'audio_es.mp4',
                'language' => 'es',
            ])
            ->withMpdOutput('manifest.mpd')
            ->export();
    }

    /**
     * Example 7: Accessing the builder for advanced configuration
     */
    public function advancedBuilderAccess(): void
    {
        $shaka = Streamer::open('input.mp4');

        // Access the builder directly for advanced configuration
        $builder = $shaka->builder();

        // Add streams
        $builder->addVideoStream('input.mp4', 'video.mp4');
        $builder->addAudioStream('input.mp4', 'audio.mp4');

        // Configure options
        $builder->withMpdOutput('manifest.mpd');
        $builder->withSegmentDuration(6);

        // Export
        $result = $shaka->export();
    }

    /**
     * Example 8: Error handling with fluent API
     */
    public function fluentWithErrorHandling(): void
    {
        try {
            $result = Streamer::open('input.mp4')
                ->addVideoStream('input.mp4', 'video.mp4')
                ->addAudioStream('input.mp4', 'audio.mp4')
                ->withMpdOutput('manifest.mpd')
                ->export();

            logger()->info('Packaging successful', $result->toArray());
        } catch (\Foxws\Streamer\Exceptions\RuntimeException $e) {
            logger()->error('Packaging failed', ['error' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            logger()->error('No streams configured', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Example 9: Mixing different stream types
     */
    public function mixedStreamTypes(): void
    {
        $result = Streamer::open('input.mp4')
            // High quality video
            ->addVideoStream('input.mp4', 'video_high.mp4', [
                'bandwidth' => '8000000',
            ])
            // Medium quality video
            ->addVideoStream('input.mp4', 'video_medium.mp4', [
                'bandwidth' => '4000000',
            ])
            // Low quality video
            ->addVideoStream('input.mp4', 'video_low.mp4', [
                'bandwidth' => '2000000',
            ])
            // High quality audio
            ->addAudioStream('input.mp4', 'audio_high.mp4', [
                'bitrate' => '192000',
            ])
            // Low quality audio
            ->addAudioStream('input.mp4', 'audio_low.mp4', [
                'bitrate' => '96000',
            ])
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(4)
            ->export();
    }

    /**
     * Example 10: Adding WebVTT subtitles
     */
    public function addingSubtitles(): void
    {
        $result = Streamer::open('input.mp4')
            ->export()
            ->addVideoStream('input.mp4', 'video.mp4')
            ->addAudioStream('input.mp4', 'audio.mp4')
            ->addStream([
                'in' => 'subtitles_en.vtt',
                'stream' => 'text',
                'output' => 'subtitles_en.vtt',
                'language' => 'en',
            ])
            ->addStream([
                'in' => 'subtitles_es.vtt',
                'stream' => 'text',
                'output' => 'subtitles_es.vtt',
                'language' => 'es',
            ])
            ->withHlsMasterPlaylist('master.m3u8')
            ->toDisk('export')
            ->save();
    }

    /**
     * Example 11: Custom output path with UUID
     */
    public function customOutputPath(): void
    {
        $uuid = '01234567-89ab-cdef-0123-456789abcdef';

        $result = Streamer::fromDisk('videos')
            ->open('source/input.mp4')
            ->export()
            ->outputPath($uuid)  // Files saved to: {uuid}/master.m3u8, {uuid}/video.mp4, etc.
            ->addVideoStream('source/input.mp4', 'video.mp4')
            ->addAudioStream('source/input.mp4', 'audio.mp4')
            ->withHlsMasterPlaylist('master.m3u8')
            ->toDisk('export')
            ->save();
    }

    /**
     * Example 12: Configuring segment duration for streaming
     */
    public function configuringSegmentDuration(): void
    {
        // Shorter segments (2s) - better for live streaming, more seeking precision
        $liveResult = Streamer::open('input.mp4')
            ->export()
            ->addVideoStream('input.mp4', 'video.mp4')
            ->addAudioStream('input.mp4', 'audio.mp4')
            ->withHlsMasterPlaylist('live.m3u8')
            ->withSegmentDuration(2)  // 2 second segments
            ->toDisk('export')
            ->save();

        // Longer segments (10s) - better for VOD, reduces overhead
        $vodResult = Streamer::open('input.mp4')
            ->export()
            ->addVideoStream('input.mp4', 'video.mp4')
            ->addAudioStream('input.mp4', 'audio.mp4')
            ->withHlsMasterPlaylist('vod.m3u8')
            ->withSegmentDuration(10)  // 10 second segments
            ->toDisk('export')
            ->save();

        // Default (6s) - balanced for most use cases
        $balancedResult = Streamer::open('input.mp4')
            ->export()
            ->addVideoStream('input.mp4', 'video.mp4')
            ->addAudioStream('input.mp4', 'audio.mp4')
            ->withHlsMasterPlaylist('balanced.m3u8')
            ->withSegmentDuration(6)  // Default: 6 second segments
            ->toDisk('export')
            ->save();
    }

    /**
     * Example 13: Reusing the packager with different configurations
     */
    public function reusingPackager(Packager $packager): void
    {
        $mediaCollection = MediaCollection::make([
            Media::make('videos', 'input.mp4'),
        ]);

        // First packaging operation
        $result1 = $packager
            ->open($mediaCollection)
            ->addVideoStream('input.mp4', 'output1/video.mp4')
            ->withMpdOutput('output1/manifest.mpd')
            ->export();

        // Get a fresh packager instance for the next operation
        $freshPackager = $packager->fresh();

        // Second packaging operation with different configuration
        $result2 = $freshPackager
            ->open($mediaCollection)
            ->addVideoStream('input.mp4', 'output2/video.mp4')
            ->addAudioStream('input.mp4', 'output2/audio.mp4')
            ->withHlsMasterPlaylist('output2/master.m3u8')
            ->export();
    }
}

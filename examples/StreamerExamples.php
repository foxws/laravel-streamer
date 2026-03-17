<?php

declare(strict_types=1);

namespace Foxws\Streamer\Examples;

use Foxws\Streamer\Exceptions\RuntimeException;
use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\Filesystem\MediaCollection;
use Foxws\Streamer\Support\CommandBuilder;
use Foxws\Streamer\Support\Stream;
use Foxws\Streamer\Support\Streamer;

/**
 * Examples of how to use the Streamer with different approaches
 */
class StreamerExamples
{
    /**
     * Example 1: Simple packaging with array-based streams
     */
    public function basicPackaging(Streamer $packager): void
    {
        $mediaCollection = MediaCollection::make([
            Media::make('videos', 'input.mp4'),
        ]);

        $packager->open($mediaCollection);

        // Package with array-based stream definitions
        $result = $packager->package([
            [
                'in' => 'storage/videos/input.mp4',
                'stream' => 'video',
                'output' => 'video_1080p.mp4',
            ],
            [
                'in' => 'storage/videos/input.mp4',
                'stream' => 'audio',
                'output' => 'audio.mp4',
            ],
        ], 'manifest.mpd');

        // Access the result
        $outputPath = $result->getMetadataValue('output_path');
    }

    /**
     * Example 2: Using Stream objects for type safety
     */
    public function streamObjectPackaging(Streamer $packager): void
    {
        $mediaCollection = MediaCollection::make([
            Media::make('videos', 'input.mp4'),
        ]);

        $packager->open($mediaCollection);

        // Get the media
        $media = $mediaCollection->collection()->first();

        // Create stream objects
        $videoStream = Stream::video($media)
            ->setOutput('video_1080p.mp4')
            ->addOption('bandwidth', '5000000');

        $audioStream = Stream::audio($media)
            ->setOutput('audio.mp4')
            ->addOption('language', 'en');

        // Convert streams to command strings
        $streams = [
            $videoStream->toCommandString(),
            $audioStream->toCommandString(),
        ];

        $result = $packager->package($streams, 'manifest.mpd');
    }

    /**
     * Example 3: Using CommandBuilder for complex operations
     */
    public function commandBuilderPackaging(Streamer $packager): void
    {
        $mediaCollection = MediaCollection::make([
            Media::make('videos', 'input.mp4'),
        ]);

        $packager->open($mediaCollection);

        $media = $mediaCollection->collection()->first();
        $inputPath = $media->getLocalPath();

        // Build a complex command with fluent API
        $builder = CommandBuilder::make()
            ->addVideoStream($inputPath, 'video_1080p.mp4', [
                'bandwidth' => '5000000',
            ])
            ->addVideoStream($inputPath, 'video_720p.mp4', [
                'bandwidth' => '3000000',
            ])
            ->addAudioStream($inputPath, 'audio_en.mp4', [
                'language' => 'en',
            ])
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(6);

        $result = $packager->packageWithBuilder($builder);
    }

    /**
     * Example 4: HLS packaging with encryption
     */
    public function hlsWithEncryption(Streamer $packager): void
    {
        $mediaCollection = MediaCollection::make([
            Media::make('videos', 'input.mp4'),
        ]);

        $packager->open($mediaCollection);

        $media = $mediaCollection->collection()->first();
        $inputPath = $media->getLocalPath();

        $builder = CommandBuilder::make()
            ->addVideoStream($inputPath, 'video.m3u8')
            ->addAudioStream($inputPath, 'audio.m3u8')
            ->withHlsMasterPlaylist('master.m3u8')
            ->withSegmentDuration(6)
            ->withEncryption([
                'keys' => 'label=:key_id=abcdef0123456789abcdef0123456789:key=0123456789abcdef0123456789abcdef',
                'key_server_url' => 'https://example.com/license',
            ]);

        $result = $packager->packageWithBuilder($builder);
    }

    /**
     * Example 5: Multiple input files (concatenation or multi-angle)
     */
    public function multipleInputFiles(Streamer $packager): void
    {
        $mediaCollection = MediaCollection::make([
            Media::make('videos', 'input1.mp4'),
            Media::make('videos', 'input2.mp4'),
            Media::make('videos', 'input3.mp4'),
        ]);

        $packager->open($mediaCollection);

        $builder = CommandBuilder::make();

        // Add streams from each media file
        foreach ($mediaCollection->collection() as $index => $media) {
            $inputPath = $media->getLocalPath();
            $builder->addVideoStream($inputPath, "video_{$index}.mp4");
            $builder->addAudioStream($inputPath, "audio_{$index}.mp4");
        }

        $builder->withMpdOutput('manifest.mpd');

        $result = $packager->packageWithBuilder($builder);
    }

    /**
     * Example 6: Adaptive bitrate streaming with multiple resolutions
     */
    public function adaptiveBitrateStreaming(Streamer $packager): void
    {
        $mediaCollection = MediaCollection::make([
            Media::make('videos', 'input.mp4'),
        ]);

        $packager->open($mediaCollection);

        $media = $mediaCollection->collection()->first();
        $inputPath = $media->getLocalPath();

        $builder = CommandBuilder::make()
            // 1080p video
            ->addStream([
                'in' => $inputPath,
                'stream' => 'video',
                'output' => 'video_1080p.mp4',
                'bandwidth' => '5000000',
            ])
            // 720p video
            ->addStream([
                'in' => $inputPath,
                'stream' => 'video',
                'output' => 'video_720p.mp4',
                'bandwidth' => '3000000',
            ])
            // 480p video
            ->addStream([
                'in' => $inputPath,
                'stream' => 'video',
                'output' => 'video_480p.mp4',
                'bandwidth' => '1500000',
            ])
            // Audio
            ->addAudioStream($inputPath, 'audio.mp4')
            ->withMpdOutput('manifest.mpd')
            ->withSegmentDuration(4);

        $result = $packager->packageWithBuilder($builder);
    }

    /**
     * Example 7: Using the streams() helper method
     */
    public function usingStreamsHelper(Streamer $packager): void
    {
        $mediaCollection = MediaCollection::make([
            Media::make('videos', 'input.mp4'),
        ]);

        $packager->open($mediaCollection);

        // Get auto-generated streams (video + audio for each media)
        $streams = $packager->streams();

        // Customize streams as needed
        $customizedStreams = $streams->map(function (Stream $stream, $index) {
            $stream->setOutput("{$stream->getType()}_{$index}.mp4");

            if ($stream->getType() === 'video') {
                $stream->addOption('bandwidth', '5000000');
            }

            return $stream->toCommandString();
        });

        $result = $packager->package($customizedStreams->all(), 'manifest.mpd');
    }

    /**
     * Example 8: Error handling
     */
    public function withErrorHandling(Streamer $packager): void
    {
        try {
            $mediaCollection = MediaCollection::make([
                Media::make('videos', 'input.mp4'),
            ]);

            $packager->open($mediaCollection);

            $builder = CommandBuilder::make()
                ->addVideoStream('input.mp4', 'output.mp4')
                ->withMpdOutput('manifest.mpd');

            $result = $packager->packageWithBuilder($builder);

            if ($result->getMetadataValue('output_path')) {
                // Success
                logger()->info('Packaging successful', $result->toArray());
            }
        } catch (RuntimeException $e) {
            // Packager command failed
            logger()->error('Packaging failed', [
                'exception' => $e->getMessage(),
            ]);
        } catch (\InvalidArgumentException $e) {
            // Invalid input (empty media collection, etc.)
            logger()->error('Invalid input', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

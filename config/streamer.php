<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Shaka Streamer Binary
    |--------------------------------------------------------------------------
    |
    | Path or command to execute the Shaka Streamer binary.
    |
    */

    'streamer' => [
        'streamer_binary' => env('STREAMER_BINARY', 'shaka-streamer'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Force Generic Input
    |--------------------------------------------------------------------------
    |
    | Whether to force using generic input paths for media files.
    |
    */

    'force_generic_input' => env('STREAMER_FORCE_GENERIC_INPUT', true),

    /*
    |--------------------------------------------------------------------------
    | Streaming Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout for the streaming process in seconds.
    | Default: 14400 seconds (4 hours)
    |
    */

    'timeout' => env('STREAMER_TIMEOUT', 14400),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | Log channel for streamer output. Set to false to disable logging.
    |
    */

    'log_channel' => env('STREAMER_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),

    /*
    |--------------------------------------------------------------------------
    | Temporary Files Root
    |--------------------------------------------------------------------------
    |
    | Root directory for temporary files used during streaming.
    |
    */

    'temporary_files_root' => env('STREAMER_TEMPORARY_FILES_ROOT', storage_path('app/streamer/temp')),

    /*
    |--------------------------------------------------------------------------
    | Cache Files Root
    |--------------------------------------------------------------------------
    |
    | Cache storage directory for small files (e.g., RAM disk like /dev/shm).
    | Used for encryption keys, manifests, and other small files that benefit
    | from faster I/O. NOT used for large video files - those use
    | temporary_files_root to avoid consuming RAM. Set to null to disable
    | and use temporary_files_root for all operations.
    |
    */

    'cache_files_root' => env('STREAMER_CACHE_FILES_ROOT', '/dev/shm'),

    /*
    |--------------------------------------------------------------------------
    | Audio Codecs
    |--------------------------------------------------------------------------
    |
    | Default audio codecs to use for streaming. This can be overridden
    | on a per-stream basis when adding streams.
    |
    | Common options: 'aac', 'opus', 'mp3'
    | Specify as comma-separated string: STREAMER_AUDIO_CODECS="aac,opus"
    |
    */

    'audio_codecs' => env('STREAMER_AUDIO_CODECS', 'aac'),

    /*
    |--------------------------------------------------------------------------
    | Video Codecs
    |--------------------------------------------------------------------------
    |
    | Default video codecs to use for streaming. This can be overridden
    | on a per-stream basis when adding streams.
    |
    | Common options: 'h264', 'hw:h264', 'vp9', 'hw:vp9', 'av1'
    | Prefix with 'hw:' for hardware-accelerated encoding.
    | Specify as comma-separated string: STREAMER_VIDEO_CODECS="hw:h264,hw:vp9"
    |
    */

    'video_codecs' => env('STREAMER_VIDEO_CODECS', 'h264'),

    /*
    |--------------------------------------------------------------------------
    | Resolutions
    |--------------------------------------------------------------------------
    |
    | Default resolutions to generate for streaming.
    | An array of strings, each representing a resolution (e.g., '1080p', '720p').
    |
    | Common options: '2160p', '1080p', '720p', '480p', '360p'
    | Leave empty to use source resolution only.
    | Specify as comma-separated string: STREAMER_RESOLUTIONS="1080p,720p,480p"
    |
    */

    'resolutions' => env('STREAMER_RESOLUTIONS', null),

    /*
    |--------------------------------------------------------------------------
    | Segment Duration
    |--------------------------------------------------------------------------
    |
    | Default duration of each segment in the stream, in seconds.
    | A typical value is between 4 and 10 seconds.
    |
    | Lower values: faster seeking, more HTTP requests
    | Higher values: fewer HTTP requests, slower seeking
    |
    */

    'segment_duration' => (int) env('STREAMER_SEGMENT_DURATION', 10),

    /*
    |--------------------------------------------------------------------------
    | Shaka Streamer Options
    |--------------------------------------------------------------------------
    |
    | Additional configuration options for Shaka Streamer.
    | See: https://shaka-project.github.io/shaka-streamer/configuration_fields.html
    |
    | These options are merged with the pipeline configuration.
    |
    */

    'streamer_options' => [],

    /*
    |--------------------------------------------------------------------------
    | Concurrency Workers
    |--------------------------------------------------------------------------
    |
    | Number of parallel child processes used when uploading streamed files
    | to the target disk (e.g. S3). Each worker handles a chunk of files
    | concurrently via Laravel's Concurrency facade.
    |
    | Higher values can improve upload throughput for large HLS/DASH outputs
    | with many segments, but consume more system resources. A value between
    | 10 and 30 is recommended for most setups.
    |
    */

    'concurrency_workers' => (int) env('STREAMER_CONCURRENCY_WORKERS', 10),

    /*
    |--------------------------------------------------------------------------
    | Concurrency Worker Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds each concurrent child process (used when
    | uploading streamed files to the target disk) may run before being
    | considered timed out. Each worker uploads a chunk of segments, so
    | large outputs may need a higher value. Set to null for no timeout.
    |
    | Default: 3600 seconds (1 hour)
    |
    */

    'concurrency_timeout' => (int) env('STREAMER_CONCURRENCY_TIMEOUT', 3600),

];

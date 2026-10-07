<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Shaka Streamer
|--------------------------------------------------------------------------
|
| Temporary files, logging, disks and the ffmpeg and ffprobe that Shaka
| Streamer runs with system binaries come from laravel-media's
| config/media.php.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Binary
    |--------------------------------------------------------------------------
    |
    | The shaka-streamer binary: a name looked up in the PATH, or a full path
    | such as /opt/venv/bin/shaka-streamer. "php artisan media:info" shows
    | what was found.
    |
    */

    'binary' => env('STREAMER_BINARY', 'shaka-streamer'),

    /*
    |--------------------------------------------------------------------------
    | System binaries
    |--------------------------------------------------------------------------
    |
    | Run the FFmpeg and Shaka Packager on the PATH instead of the ones from
    | shaka-streamer-binaries. laravel-media's ffmpeg and ffprobe, and the
    | packager below, go first in that PATH. The packager must be named
    | "packager".
    |
    */

    'system_binaries' => (bool) env('STREAMER_SYSTEM_BINARIES', false),

    'packager' => env('STREAMER_PACKAGER_BINARY'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | The seconds one Shaka Streamer run may take. Encoding several qualities
    | of a long video can take hours, so keep this at or below your queue
    | job's $timeout.
    |
    */

    'timeout' => (int) env('STREAMER_TIMEOUT', 14400),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Applied to every run; the builder's methods override them. Leave an
    | option null to use Shaka Streamer's own default.
    |
    | video_codecs: comma-separated, e.g. "h264,vp9"; prefix "hw:" for
    |     hardware encoding, e.g. "hw:h264"
    | audio_codecs: comma-separated, e.g. "aac,opus"
    | segment_duration: seconds per segment
    | hardware_acceleration: the API for "hw:" codecs, e.g. "vaapi", "nvenc"
    |     or "videotoolbox"
    | ffmpeg_input_args: FFmpeg arguments for every input
    |
    */

    'video_codecs' => env('STREAMER_VIDEO_CODECS', 'h264'),

    'audio_codecs' => env('STREAMER_AUDIO_CODECS', 'aac'),

    'segment_duration' => env('STREAMER_SEGMENT_DURATION', 6),

    'hardware_acceleration' => env('STREAMER_HARDWARE_ACCELERATION'),

    'ffmpeg_input_args' => env('STREAMER_FFMPEG_INPUT_ARGS'),

];

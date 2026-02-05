<?php

declare(strict_types=1);

return [

    /**
     * Path to the packager binary and other related settings.
     */
    'packager' => [
        'binaries' => env('PACKAGER_PATH', '/usr/local/bin/packager'),
    ],

    /**
     * Whether to force using generic input paths for media files.
     */
    'force_generic_input' => env('PACKAGER_FORCE_GENERIC_INPUT', true),

    /**
     * Timeout for the packaging process in seconds.
     */
    'timeout' => 60 * 60 * 4, // 4 hours

    /**
     * Log channel for packager output. Set to false to disable logging.
     */
    'log_channel' => env('PACKAGER_LOG_CHANNEL', null),

    /**
     * Root directory for temporary files used during packaging.
     */
    'temporary_files_root' => env('PACKAGER_TEMPORARY_FILES_ROOT', storage_path('app/packager/temp')),

    /**
     * Cache storage directory for small files (e.g., RAM disk like /dev/shm).
     * Used for encryption keys, manifests, and other small files that benefit from faster I/O.
     * NOT used for large video files - those use temporary_files_root to avoid consuming RAM.
     * Set to null to disable and use temporary_files_root for all operations.
     */
    'cache_files_root' => env('PACKAGER_CACHE_FILES_ROOT', '/dev/shm'),

];

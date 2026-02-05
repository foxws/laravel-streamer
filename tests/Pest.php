<?php

declare(strict_types=1);

use Foxws\Streamer\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Check if Shaka Packager binary is available for testing
 */
function hasPackager(): bool
{
    $binary = config('laravel-streamer.packager.binaries', '/usr/local/bin/packager');

    return file_exists($binary) && is_executable($binary);
}

/**
 * Skip test if Shaka Packager is not installed
 */
function skipIfNoPackager(): void
{
    if (! hasPackager()) {
        test()->markTestSkipped('Shaka Packager binary not available');
    }
}

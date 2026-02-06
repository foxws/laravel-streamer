<?php

declare(strict_types=1);

use Foxws\Streamer\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Check if Shaka Streamer is available for testing
 */
function hasStreamer(): bool
{
    $streamerBinary = config('streamer.streamer.streamer_binary', 'shaka-streamer');

    try {
        // Check if Python is available
        $pythonCheck = shell_exec('python3 --version 2>&1');

        if (! $pythonCheck) {
            return false;
        }

        // Check if Shaka Streamer is installed via pip
        $streamerCheck = shell_exec("{$streamerBinary} --version 2>&1");

        return ! empty($streamerCheck);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Skip test if Shaka Streamer is not available
 */
function skipIfNoStreamer(): void
{
    if (! hasStreamer()) {
        test()->markTestSkipped('Shaka Streamer not available');
    }
}

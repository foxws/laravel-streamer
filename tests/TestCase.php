<?php

declare(strict_types=1);

namespace Foxws\Streamer\Tests;

use Foxws\Media\MediaServiceProvider;
use Foxws\Streamer\StreamerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MediaServiceProvider::class,
            StreamerServiceProvider::class,
        ];
    }
}

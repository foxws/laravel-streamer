<?php

declare(strict_types=1);

namespace Foxws\Streamer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Foxws\Streamer\MediaOpener fromDisk($disk)
 * @method static \Foxws\Streamer\MediaOpener open($path)
 * @method static \Foxws\Streamer\MediaOpener openFromDisk($disk, $path)
 * @method static \Foxws\Streamer\MediaOpener cleanupTemporaryFiles()
 * @method static \Foxws\Streamer\Exporters\MediaExporter export()
 * @method static \Foxws\Streamer\Http\DynamicHLSPlaylist dynamicHLSPlaylist(?string $disk = null)
 * @method static \Foxws\Streamer\Http\DynamicDASHManifest dynamicDASHManifest(?string $disk = null)
 *
 * @see \Foxws\Streamer\MediaOpener
 */
class Streamer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-streamer';
    }
}

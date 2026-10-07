<?php

declare(strict_types=1);

namespace Foxws\Streamer\Testing;

use Foxws\Media\Testing\MediaFake;
use Foxws\Streamer\StreamerExecutable;
use Illuminate\Filesystem\Filesystem;

/**
 * Answers Shaka Streamer in Media::fake(): the manifests of the pipeline config and a placeholder
 * segment per resolution are written to the output directory, so the export saves them.
 */
final class FakeStreamer
{
    public static function respond(MediaFake $fake): MediaFake
    {
        return $fake->respondUsing(StreamerExecutable::ShakaStreamer, function (array $arguments): string {
            $pipeline = self::option($arguments, '-p');
            $output = self::option($arguments, '-o');

            if ($pipeline === null || $output === null || ! is_file($pipeline)) {
                return '';
            }

            $config = json_decode((string) file_get_contents($pipeline), true);
            $config = is_array($config) ? $config : [];
            $resolutions = is_array($config['resolutions'] ?? null) ? $config['resolutions'] : ['source'];

            $files = [
                ...array_filter([$config['hls_output'] ?? null, $config['dash_output'] ?? null], is_string(...)),
                ...array_map(fn (mixed $resolution): string => 'video_'.(is_scalar($resolution) ? (string) $resolution : 'source').'.mp4', $resolutions),
            ];

            foreach ($files as $file) {
                new Filesystem()->ensureDirectoryExists(dirname("{$output}/{$file}"));
                file_put_contents("{$output}/{$file}", 'fake media');
            }

            return '';
        });
    }

    /**
     * @param  list<string>  $arguments
     */
    private static function option(array $arguments, string $name): ?string
    {
        $index = array_search($name, $arguments, true);

        return $index !== false ? ($arguments[$index + 1] ?? null) : null;
    }
}

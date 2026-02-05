<?php

declare(strict_types=1);

namespace Foxws\Streamer\Commands;

use Foxws\Streamer\Support\ShakaStreamer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;

class PackageInfoCommand extends Command
{
    protected $signature = 'shaka:info';

    protected $description = 'Display Laravel Shaka Packager package information';

    public function handle(): int
    {
        info('Laravel Shaka Packager');

        // Package version
        $composerPath = base_path('vendor/foxws/laravel-streamer/composer.json');

        $packageVersion = 'dev-main';

        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            $packageVersion = $composer['version'] ?? 'dev-main';
        }

        // Packager version
        $packagerVersion = 'Not available';

        try {
            $packager = ShakaStreamer::create();
        } catch (\Exception $e) {
            // Keep as "Not available"
        }

        note("Package Version: {$packageVersion}");
        note("Packager Version: {$packagerVersion}");

        // Configuration table
        $logChannel = Config::get('laravel-streamer.log_channel');

        $logStatus = $logChannel === false ? 'Disabled' : ($logChannel ?: 'Default');

        $forceGeneric = Config::get('laravel-streamer.force_generic_input') ? 'Enabled' : 'Disabled';
        $streamerBinary = Config::get('laravel-streamer.streamer.streamer_binary', 'shaka-streamer');

        table(
            ['Configuration', 'Value'],
            [
                ['Streamer Binary', $streamerBinary],
                ['Timeout', Config::get('laravel-streamer.timeout').' seconds'],
                ['Temp Directory', Config::get('laravel-streamer.temporary_files_root')],
                ['Logging', $logStatus],
                ['Force Generic Input', $forceGeneric],
            ]
        );

        return self::SUCCESS;
    }
}

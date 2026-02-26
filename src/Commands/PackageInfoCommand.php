<?php

declare(strict_types=1);

namespace Foxws\Streamer\Commands;

use Composer\InstalledVersions;
use Foxws\Streamer\Exceptions\ExecutableNotFoundException;
use Foxws\Streamer\Support\ShakaStreamer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class PackageInfoCommand extends Command
{
    protected $signature = 'streamer:info';

    protected $description = 'Display package information and verify Shaka Streamer installation';

    public function handle(): int
    {
        info('Laravel Shaka Streamer - Information & Verification');

        $streamerBinary = Config::get('streamer.streamer.streamer_binary', 'shaka-streamer');
        $tempDir = Config::get('streamer.temporary_files_root', storage_path('app/streamer/temp'));
        $timeout = Config::get('streamer.timeout');
        $logChannel = Config::get('streamer.log_channel');
        $logStatus = $logChannel === false ? 'Disabled' : ($logChannel ?: Config::get('logging.default', 'Default'));

        $driverInitialized = false;
        try {
            ShakaStreamer::create();
            $driverInitialized = true;
        } catch (ExecutableNotFoundException $e) {
            error('✗ Cannot initialize Streamer driver: '.$e->getMessage());
        } catch (\Exception $e) {
            error('✗ Error initializing Streamer driver: '.$e->getMessage());
        }

        table(
            ['Setting', 'Value', 'Status'],
            [
                ['Package Version', InstalledVersions::getPrettyVersion('foxws/laravel-streamer') ?? 'dev-main', '✓'],
                ['Streamer Binary', $streamerBinary, $driverInitialized ? '✓' : '✗'],
                ['Timeout', "{$timeout}s", '✓'],
                ['Temp Directory', $tempDir, $this->getTempDirStatus($tempDir)],
                ['Logging', $logStatus, '✓'],
                ['Force Generic Input', Config::get('streamer.force_generic_input') ? 'Enabled' : 'Disabled', '✓'],
            ]
        );

        if (! is_writable($tempDir) && is_dir($tempDir)) {
            error("✗ Temporary directory is not writable: {$tempDir}");

            return self::FAILURE;
        }

        if (! is_dir($tempDir)) {
            warning('⚠ Temporary directory does not exist (will be created automatically)');
        }

        if (! $driverInitialized) {
            error('✗ Shaka Streamer is not properly configured. Please check the errors above.');

            return self::FAILURE;
        }

        info('✅ Shaka Streamer is properly configured and ready to use!');

        return self::SUCCESS;
    }

    protected function getTempDirStatus(string $tempDir): string
    {
        if (! is_dir($tempDir)) {
            return '⚠';
        }

        return is_writable($tempDir) ? '✓' : '✗';
    }
}

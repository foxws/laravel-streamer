<?php

declare(strict_types=1);

namespace Foxws\Streamer\Commands;

use Foxws\Streamer\Exceptions\ExecutableNotFoundException;
use Foxws\Streamer\Support\ShakaStreamer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class PackageInfoCommand extends Command
{
    protected $signature = 'streamer:info';

    protected $description = 'Display package information and verify Shaka Streamer installation';

    public function handle(): int
    {
        info('🔍 Laravel Shaka Packager - Information & Verification');

        // Package version
        $composerPath = base_path('vendor/foxws/laravel-streamer/composer.json');
        $packageVersion = 'dev-main';

        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            $packageVersion = $composer['version'] ?? 'dev-main';
        }

        note("Package Version: {$packageVersion}");

        // Verify streamer installation
        $streamerBinary = Config::get('laravel-streamer.streamer.streamer_binary', 'shaka-streamer');
        note("Streamer Binary: {$streamerBinary}");

        $driverInitialized = false;
        try {
            $driver = ShakaStreamer::create();
            $this->components->info('✓ Shaka Streamer driver initialized successfully');
            $driverInitialized = true;
        } catch (ExecutableNotFoundException $e) {
            error('✗ Cannot initialize Streamer driver');
            error($e->getMessage());
        } catch (\Exception $e) {
            error('✗ Error initializing Streamer driver');
            error($e->getMessage());
        }

        // Configuration details
        $timeout = Config::get('laravel-streamer.timeout');
        $logChannel = Config::get('laravel-streamer.log_channel');
        $logStatus = $logChannel === false ? 'Disabled' : ($logChannel ?: Config::get('logging.default', 'Default'));
        $tempDir = Config::get('laravel-streamer.temporary_files_root', storage_path('app/streamer/temp'));
        $forceGeneric = Config::get('laravel-streamer.force_generic_input') ? 'Enabled' : 'Disabled';

        table(
            ['Configuration', 'Value', 'Status'],
            [
                ['Streamer Binary', $streamerBinary, $driverInitialized ? '✓' : '✗'],
                ['Timeout', "{$timeout} seconds", '✓'],
                ['Temp Directory', $tempDir, $this->getTempDirStatus($tempDir)],
                ['Logging', $logStatus, '✓'],
                ['Force Generic Input', $forceGeneric, '✓'],
            ]
        );

        // Check temporary directory
        if (! is_dir($tempDir)) {
            warning('⚠ Temporary directory does not exist (will be created automatically)');
        } elseif (! is_writable($tempDir)) {
            error("✗ Temporary directory is not writable: {$tempDir}");

            return self::FAILURE;
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

        if (! is_writable($tempDir)) {
            return '✗';
        }

        return '✓';
    }
}

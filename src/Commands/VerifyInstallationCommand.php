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
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class VerifyInstallationCommand extends Command
{
    protected $signature = 'shaka:verify';

    protected $description = 'Verify Shaka Packager installation and configuration';

    public function handle(): int
    {
        info('🔍 Verifying Shaka Packager installation...');

        $binaryPath = Config::get('laravel-streamer.packager.binaries');

        note("Binary Path: {$binaryPath}");

        // Check if file exists
        if (! file_exists($binaryPath)) {
            error("Binary not found at: {$binaryPath}");
            warning('Please ensure Shaka Packager is installed and the path is correct.');
            note('Download from: https://github.com/shaka-project/shaka-packager/releases');

            return self::FAILURE;
        }

        $this->components->info('Binary exists');

        // Check if executable
        if (! is_executable($binaryPath)) {
            error('Binary is not executable');
            warning("Run: chmod +x {$binaryPath}");

            return self::FAILURE;
        }

        $this->components->info('Binary is executable');

        // Try to get version with spinner
        try {
            $version = spin(
                fn () => ShakaStreamer::create()->getVersion(),
                'Checking packager version...'
            );

            $this->components->info("Version: {$version}");
        } catch (ExecutableNotFoundException $e) {
            error('Cannot execute binary');
            error($e->getMessage());

            return self::FAILURE;
        } catch (\Exception $e) {
            error('Error getting version');
            error($e->getMessage());

            return self::FAILURE;
        }

        // Configuration details
        $timeout = Config::get('laravel-streamer.timeout');

        $logChannel = Config::get('laravel-streamer.log_channel');

        $logStatus = $logChannel === false ? 'Disabled' : ($logChannel ?: Config::get('logging.default'));

        $tempDir = Config::get('laravel-streamer.temporary_files_root');

        table(
            ['Configuration', 'Value', 'Status'],
            [
                ['Timeout', "{$timeout} seconds", '✓'],
                ['Log Channel', $logStatus, '✓'],
                ['Temp Directory', $tempDir, $this->getTempDirStatus($tempDir)],
            ]
        );

        // Check temporary directory
        if (! is_dir($tempDir)) {
            warning('Temporary directory does not exist (will be created automatically)');
        } elseif (! is_writable($tempDir)) {
            error("Temporary directory is not writable: {$tempDir}");

            return self::FAILURE;
        }

        info('✅ Shaka Packager is properly configured and ready to use!');

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

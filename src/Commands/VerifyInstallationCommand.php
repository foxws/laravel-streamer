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

class VerifyInstallationCommand extends Command
{
    protected $signature = 'shaka:verify';

    protected $description = 'Verify Shaka Streamer installation and configuration';

    public function handle(): int
    {
        info('🔍 Verifying Shaka Streamer installation...');

        $pythonBinary = Config::get('laravel-streamer.streamer.python_binary', 'python3');
        $executable = Config::get('laravel-streamer.streamer.executable', 'shaka-streamer');

        note("Python Binary: {$pythonBinary}");
        note("Streamer Executable: {$executable}");

        $this->components->info('Configuration loaded successfully');

        // Try to verify the streamer can be called
        try {
            $driver = ShakaStreamer::create();
            $this->components->info('Shaka Streamer driver initialized successfully');
        } catch (ExecutableNotFoundException $e) {
            error('Cannot initialize Streamer driver');
            error($e->getMessage());

            return self::FAILURE;
        } catch (\Exception $e) {
            error('Error initializing Streamer driver');
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

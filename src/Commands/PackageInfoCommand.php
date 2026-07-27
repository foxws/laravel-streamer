<?php

declare(strict_types=1);

namespace Foxws\Streamer\Commands;

use Composer\InstalledVersions;
use Foxws\Streamer\Support\ShakaStreamer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class PackageInfoCommand extends Command
{
    protected $signature = 'streamer:info';

    protected $description = 'Display package information and verify Shaka Streamer installation';

    public function handle(Repository $config, ShakaStreamer $streamer): int
    {
        info('Laravel Shaka Streamer - Information & Verification');

        $tempDir = $config->get('streamer.temporary_files_root', storage_path('app/streamer/temp'));
        $logChannel = $config->get('streamer.log_channel');
        $logStatus = $logChannel === false ? 'Disabled' : ($logChannel ?: $config->get('logging.default', 'Default'));

        // Actually invoke the binary (--version) so this reflects whether Shaka
        // Streamer can really run, not just whether the config resolved.
        $binaryVersion = null;

        try {
            $binaryVersion = $streamer->getVersion();
        } catch (\RuntimeException $e) {
            error("✗ Cannot execute Shaka Streamer binary: {$e->getMessage()}");
        }

        table(
            ['Setting', 'Value', 'Status'],
            [
                ['Package Version', InstalledVersions::getPrettyVersion('foxws/laravel-streamer') ?? 'dev-main', '✓'],
                ['Streamer Binary', $streamer->getStreamerBinary(), $binaryVersion ? '✓' : '✗'],
                ['Binary Version', $binaryVersion ?? 'Unknown', $binaryVersion ? '✓' : '✗'],
                ['Timeout', "{$streamer->getTimeout()}s", '✓'],
                ['Temp Directory', $tempDir, $this->getTempDirStatus($tempDir)],
                ['Logging', $logStatus, '✓'],
                ['Force Generic Input', $config->get('streamer.force_generic_input') ? 'Enabled' : 'Disabled', '✓'],
            ]
        );

        if (! is_writable($tempDir) && is_dir($tempDir)) {
            error("✗ Temporary directory is not writable: {$tempDir}");

            return self::FAILURE;
        }

        if (! is_dir($tempDir)) {
            warning('⚠ Temporary directory does not exist (will be created automatically)');
        }

        if (! $binaryVersion) {
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

<?php

declare(strict_types=1);

namespace Foxws\Streamer;

use Foxws\Streamer\Filesystem\MediaOpenerFactory;
use Foxws\Streamer\Filesystem\TemporaryDirectories;
use Foxws\Streamer\Support\ShakaStreamer;
use Foxws\Streamer\Support\Streamer;
use Illuminate\Support\Facades\Config;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class StreamerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-streamer')
            ->hasConfigFile('streamer')
            ->hasCommands([
                Commands\VerifyInstallationCommand::class,
                Commands\PackageInfoCommand::class,
            ]);
    }

    public function streamerRegistered(): void
    {
        $this->app->singleton('laravel-streamer-logger', function () {
            $logChannel = Config::get('laravel-streamer.log_channel');

            if ($logChannel === false) {
                return null;
            }

            return app('log')->channel($logChannel ?: Config::get('logging.default'));
        });

        $this->app->singleton('laravel-streamer-configuration', function () {
            $baseConfig = [
                'streamer.streamer_binary' => Config::string('laravel-streamer.streamer.streamer_binary', 'shaka-streamer'),
                'timeout' => Config::integer('laravel-streamer.timeout', 3600),
            ];

            if ($configuredTemporaryRoot = Config::string('laravel-streamer.temporary_files_root', '')) {
                $baseConfig['temporary_directory'] = $configuredTemporaryRoot;
            }

            return $baseConfig;
        });

        $this->app->singleton(TemporaryDirectories::class, function () {
            return new TemporaryDirectories(
                Config::string('laravel-streamer.temporary_files_root', sys_get_temp_dir()),
                Config::string('laravel-streamer.cache_files_root', '') ?: null,
            );
        });

        // Register the Shaka Streamer Driver
        $this->app->singleton(ShakaStreamer::class, function ($app) {
            $logger = $app->make('laravel-streamer-logger');
            $config = $app->make('laravel-streamer-configuration');

            return ShakaStreamer::create($logger, $config);
        });

        // Register the Streamer
        $this->app->singleton(Streamer::class, function ($app) {
            $driver = $app->make(ShakaStreamer::class);
            $logger = $app->make('laravel-streamer-logger');

            return new Streamer($driver, $logger);
        });

        // Register the main class to use with the facade
        $this->app->singleton('laravel-streamer', function () {
            return new MediaOpenerFactory(
                Config::string('filesystems.default'),
                null,
                fn () => app(Streamer::class)
            );
        });
    }
}

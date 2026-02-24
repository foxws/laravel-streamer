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
                Commands\PackageInfoCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('laravel-streamer-logger', function () {
            $logChannel = Config::get('streamer.log_channel');

            if ($logChannel === false) {
                return null;
            }

            return app('log')->channel($logChannel ?: Config::get('logging.default'));
        });

        $this->app->singleton('laravel-streamer-configuration', function () {
            $config = Config::get('streamer', []);

            // Add temporary_directory if configured
            if (! empty($config['temporary_files_root'])) {
                $config['temporary_directory'] = $config['temporary_files_root'];
            }

            return $config;
        });

        $this->app->singleton(TemporaryDirectories::class, function () {
            return new TemporaryDirectories(
                Config::string('streamer.temporary_files_root', sys_get_temp_dir()),
                Config::string('streamer.cache_files_root', '') ?: null,
            );
        });

        // Register the Shaka Streamer Driver
        $this->app->singleton(ShakaStreamer::class, function ($app) {
            $logger = $app->make('laravel-streamer-logger');
            $config = $app->make('laravel-streamer-configuration');

            return ShakaStreamer::create($logger, $config);
        });

        // Register Laravel Streamer
        $this->app->scoped(Streamer::class, function ($app) {
            $driver = $app->make(ShakaStreamer::class);
            $logger = $app->make('laravel-streamer-logger');
            $config = $app->make('laravel-streamer-configuration');

            return new Streamer($driver, $logger, $config);
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

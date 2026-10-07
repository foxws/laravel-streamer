<?php

declare(strict_types=1);

namespace Foxws\Streamer;

use Foxws\Media\Executables\Executables;
use Foxws\Media\Opener;
use Illuminate\Support\ServiceProvider;

class StreamerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/streamer.php', 'streamer');

        Opener::macro('streamer', function (): StreamerBuilder {
            /** @var Opener $this */
            return app(StreamerBuilder::class, ['opener' => $this]);
        });
    }

    public function boot(): void
    {
        $this->app->make(Executables::class)->register(StreamerExecutable::ShakaStreamer);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/streamer.php' => config_path('streamer.php'),
        ], ['streamer', 'streamer-config']);
    }
}

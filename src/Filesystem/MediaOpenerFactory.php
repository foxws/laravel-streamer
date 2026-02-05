<?php

declare(strict_types=1);

namespace Foxws\Streamer\Filesystem;

use Closure;
use Foxws\Streamer\MediaOpener;
use Foxws\Streamer\Support\Streamer;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaOpenerFactory
{
    use ForwardsCalls;

    protected ?string $defaultDisk = null;

    protected ?Streamer $streamer = null;

    protected ?Closure $streamerResolver = null;

    public function __construct(
        ?string $defaultDisk = null,
        ?Streamer $streamer = null,
        ?Closure $streamerResolver = null
    ) {
        $this->defaultDisk = $defaultDisk;
        $this->streamer = $streamer;
        $this->streamerResolver = $streamerResolver;
    }

    protected function streamer(): Streamer
    {
        if ($this->streamer) {
            return $this->streamer;
        }

        $resolver = $this->streamerResolver;

        return $this->streamer = $resolver();
    }

    public function new(): MediaOpener
    {
        return new MediaOpener($this->defaultDisk, $this->streamer());
    }

    /**
     * Handle dynamic method calls into Shaka.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->new(), $method, $parameters);
    }
}

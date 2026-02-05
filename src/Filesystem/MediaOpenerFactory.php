<?php

declare(strict_types=1);

namespace Foxws\Streamer\Filesystem;

use Closure;
use Foxws\Streamer\MediaOpener;
use Foxws\Streamer\Support\Packager;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaOpenerFactory
{
    use ForwardsCalls;

    protected ?string $defaultDisk = null;

    protected ?Packager $packager = null;

    protected ?Closure $packagerResolver = null;

    public function __construct(
        ?string $defaultDisk = null,
        ?Packager $packager = null,
        ?Closure $packagerResolver = null
    ) {
        $this->defaultDisk = $defaultDisk;
        $this->packager = $packager;
        $this->packagerResolver = $packagerResolver;
    }

    protected function packager(): Packager
    {
        if ($this->packager) {
            return $this->packager;
        }

        $resolver = $this->packagerResolver;

        return $this->packager = $resolver();
    }

    public function new(): MediaOpener
    {
        return new MediaOpener($this->defaultDisk, $this->packager());
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

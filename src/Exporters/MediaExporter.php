<?php

declare(strict_types=1);

namespace Foxws\Streamer\Exporters;

use Foxws\Streamer\Filesystem\Disk;
use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\MediaOpener;
use Foxws\Streamer\Support\Packager;
use Foxws\Streamer\Support\PackagerResult;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaExporter
{
    use ForwardsCalls;

    protected ?Packager $packager = null;

    protected ?Disk $toDisk = null;

    protected ?string $visibility = null;

    protected ?string $toPath = null;

    protected ?array $afterSavingCallbacks = [];

    public function __construct(Packager $packager)
    {
        $this->packager = $packager;
    }

    protected function getDisk(): Disk
    {
        if ($this->toDisk) {
            return $this->toDisk;
        }

        $media = $this->packager->getMediaCollection();

        /** @var Disk $disk */
        $disk = $media->first()->getDisk();

        return $this->toDisk = $disk->clone();
    }

    public function toDisk($disk): self
    {
        $this->toDisk = Disk::make($disk);

        return $this;
    }

    public function toPath(string $path): self
    {
        $this->toPath = rtrim($path, '/').'/';

        return $this;
    }

    public function withVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * Returns the final command, useful for debugging purposes.
     */
    public function getCommand(): string
    {
        return $this->packager->getCommand();
    }

    /**
     * Dump the final command and end the script.
     */
    public function dd(): void
    {
        dd($this->getCommand());
    }

    /**
     * Adds a callable to the callbacks array.
     */
    public function afterSaving(callable $callback): self
    {
        $this->afterSavingCallbacks[] = $callback;

        return $this;
    }

    protected function prepareSaving(?string $path = null): ?Media
    {
        $outputMedia = $path ? $this->getDisk()->makeMedia($path) : null;

        return $outputMedia;
    }

    protected function runAfterSavingCallbacks(PackagerResult $result)
    {
        if (empty($this->afterSavingCallbacks)) {
            return;
        }

        foreach ($this->afterSavingCallbacks as $key => $callback) {
            call_user_func($callback, $this, $result);

            unset($this->afterSavingCallbacks[$key]);
        }
    }

    public function save(?string $path = null): MediaOpener
    {
        // Execute the packaging operation (writes to temporary directory)
        $result = $this->packager->export();

        // Determine target disk
        $targetDisk = $this->toDisk ?: $this->getDisk();

        // Copy outputs from temporary directory to target disk and cleanup
        $result->toDisk($targetDisk, $this->visibility, true, $this->toPath);

        $this->runAfterSavingCallbacks($result);

        return $this->getMediaOpener();
    }

    protected function getMediaOpener(): MediaOpener
    {
        return new MediaOpener(
            $this->packager->getMediaCollection()->last()->getDisk()->getName(),
            $this->packager,
            $this->packager->getMediaCollection()
        );
    }

    /**
     * Forwards the call to the driver object and returns the result
     * if it's something different than the driver object itself.
     */
    public function __call($method, $arguments)
    {
        $result = $this->forwardCallTo($packager = $this->packager, $method, $arguments);

        return ($result === $packager) ? $this : $result;
    }
}

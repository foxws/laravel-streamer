<?php

declare(strict_types=1);

namespace Foxws\Streamer\Exporters;

use Foxws\Streamer\Filesystem\Disk;
use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\MediaOpener;
use Foxws\Streamer\Support\Streamer;
use Foxws\Streamer\Support\StreamerResult;
use Illuminate\Support\Traits\ForwardsCalls;

class MediaExporter
{
    use ForwardsCalls;

    protected ?Streamer $streamer = null;

    protected ?Disk $toDisk = null;

    protected ?string $visibility = null;

    protected ?string $toPath = null;

    protected ?array $afterSavingCallbacks = [];

    public function __construct(Streamer $streamer)
    {
        $this->streamer = $streamer;
    }

    protected function getDisk(): Disk
    {
        if ($this->toDisk) {
            return $this->toDisk;
        }

        $media = $this->streamer->getMediaCollection();

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
     * Returns the final config, useful for debugging purposes.
     */
    public function getCommand(): array
    {
        return $this->streamer->getCommand();
    }

    /**
     * Dump the final config and end the script.
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

    protected function runAfterSavingCallbacks(StreamerResult $result)
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
        $result = $this->streamer->export();

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
            $this->streamer->getMediaCollection()->last()->getDisk()->getName(),
            $this->streamer,
            $this->streamer->getMediaCollection()
        );
    }

    /**
     * Forwards the call to the driver object and returns the result
     * if it's something different than the driver object itself.
     */
    public function __call($method, $arguments)
    {
        $result = $this->forwardCallTo($streamer = $this->streamer, $method, $arguments);

        return ($result === $streamer) ? $this : $result;
    }
}

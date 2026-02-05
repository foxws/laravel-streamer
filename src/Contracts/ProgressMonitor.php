<?php

declare(strict_types=1);

namespace Foxws\Streamer\Contracts;

interface ProgressMonitor
{
    /**
     * Called when packaging starts
     */
    public function onStart(array $context): void;

    /**
     * Called when packaging progresses
     *
     * @param  float  $percentage  Progress percentage (0-100)
     */
    public function onProgress(float $percentage, array $context): void;

    /**
     * Called when packaging completes
     */
    public function onComplete(array $context): void;

    /**
     * Called when packaging fails
     */
    public function onError(\Exception $exception, array $context): void;
}

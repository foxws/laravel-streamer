<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

it('reports success when the Shaka Streamer binary is executable', function () {
    Process::fake([
        '*' => Process::result(output: 'shaka-streamer version 1.4.2'),
    ]);

    $this->artisan('streamer:info')
        ->expectsOutputToContain('Shaka Streamer is properly configured')
        ->assertExitCode(0);
});

it('reports failure when the Shaka Streamer binary cannot be executed', function () {
    Process::fake([
        '*' => Process::result(errorOutput: 'command not found', exitCode: 127),
    ]);

    $this->artisan('streamer:info')
        ->expectsOutputToContain('Cannot execute Shaka Streamer binary')
        ->assertExitCode(1);
});

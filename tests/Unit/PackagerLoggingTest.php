<?php

declare(strict_types=1);

use Foxws\Streamer\Support\CommandBuilder;
use Foxws\Streamer\Support\Packager;
use Foxws\Streamer\Support\ShakaPackager;
use Psr\Log\LoggerInterface;

it('filters sensitive encryption keys from logs', function () {
    $logger = mock(LoggerInterface::class);
    $driver = mock(ShakaPackager::class);

    // Mock the driver to return a successful result
    $driver->shouldReceive('command')
        ->once()
        ->andReturn('success');

    $packager = new Packager($driver, $logger);

    // Create a builder with encryption options including sensitive keys
    $builder = CommandBuilder::make()
        ->addVideoStream('/tmp/input.mp4', '/tmp/output.mp4')
        ->withEncryption([
            'keys' => 'label=:key_id=abcdef0123456789abcdef0123456789:key=0123456789abcdef0123456789abcdef',
            'key_server_url' => 'https://example.com/license',
        ]);

    // Expect the logger to be called with redacted sensitive data
    $logger->shouldReceive('info')
        ->once()
        ->with('Starting packaging operation with builder', \Mockery::on(function ($context) {
            // Check that sensitive keys are redacted
            expect($context)->toHaveKey('options');
            expect($context['options'])->toHaveKey('keys');
            expect($context['options']['keys'])->toBe('[REDACTED]');
            // Check that non-sensitive data is still present
            expect($context['options'])->toHaveKey('key_server_url');
            expect($context['options']['key_server_url'])->toBe('https://example.com/license');

            return true;
        }));

    $logger->shouldReceive('info')
        ->once()
        ->with('Packaging operation completed');

    $packager->packageWithBuilder($builder);
});

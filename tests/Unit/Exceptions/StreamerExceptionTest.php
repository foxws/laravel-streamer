<?php

declare(strict_types=1);

use Foxws\Streamer\Exceptions\StreamerException;

it('explains what is missing', function (): void {
    expect(StreamerException::noManifests()->getMessage())->toContain('withHlsPlaylist()')
        ->and(StreamerException::missingManifest('master.m3u8')->getMessage())->toBe('Shaka Streamer finished without writing [master.m3u8].');
});

<?php

declare(strict_types=1);

use Foxws\Media\Facades\Media;
use Foxws\Streamer\Testing\FakeStreamer;
use Illuminate\Support\Facades\Storage;

it('writes the manifests and a placeholder per resolution', function (): void {
    Storage::fake('media');
    Storage::disk('media')->put('clip.mp4', 'video');
    $fake = FakeStreamer::respond(Media::fake());

    Media::fromDisk('media')->open('clip.mp4')
        ->streamer()
        ->addVideoStream()
        ->withResolutions('720p')
        ->withHlsPlaylist('hls/master.m3u8')
        ->withDashManifest('dash/manifest.mpd')
        ->save('out');

    $fake->assertSaved('out/hls/master.m3u8', 'media');
    $fake->assertSaved('out/dash/manifest.mpd', 'media');
    $fake->assertSaved('out/video_720p.mp4', 'media');
    expect(Storage::disk('media')->get('out/video_720p.mp4'))->toBe('fake media');
});

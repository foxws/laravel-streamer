<?php

declare(strict_types=1);

use Foxws\Streamer\Http\DynamicDASHManifest;
use Foxws\Streamer\Http\DynamicHLSPlaylist;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'filesystems.disks.test-disk' => [
            'driver' => 'local',
            'root' => storage_path('app/test'),
        ],
    ]);
});

// HLS Playlist Tests
it('can set key url resolver on hls playlist', function () {
    $playlist = new DynamicHLSPlaylist;

    $resolver = fn ($key) => route('video.key', ['key' => $key]);

    $result = $playlist->setKeyUrlResolver($resolver);

    expect($result)->toBe($playlist);
    expect($playlist->getKeyUrlResolver())->toBe($resolver);
});

it('can set media url resolver on hls playlist', function () {
    $playlist = new DynamicHLSPlaylist;

    $resolver = fn ($filename) => Storage::disk('public')->url($filename);

    $result = $playlist->setMediaUrlResolver($resolver);

    expect($result)->toBe($playlist);
    expect($playlist->getMediaUrlResolver())->toBe($resolver);
});

it('can set playlist url resolver on hls playlist', function () {
    $playlist = new DynamicHLSPlaylist;

    $resolver = fn ($filename) => route('video.playlist', ['playlist' => $filename]);

    $result = $playlist->setPlaylistUrlResolver($resolver);

    expect($result)->toBe($playlist);
    expect($playlist->getPlaylistUrlResolver())->toBe($resolver);
});

it('can chain multiple resolver setters on hls playlist', function () {
    $playlist = new DynamicHLSPlaylist;

    $result = $playlist
        ->setKeyUrlResolver(fn ($key) => "keys/{$key}")
        ->setMediaUrlResolver(fn ($file) => "media/{$file}")
        ->setPlaylistUrlResolver(fn ($file) => "playlists/{$file}");

    expect($result)->toBe($playlist);
    expect($playlist->getKeyUrlResolver())->not->toBeNull();
    expect($playlist->getMediaUrlResolver())->not->toBeNull();
    expect($playlist->getPlaylistUrlResolver())->not->toBeNull();
});

it('clears cache when setting new resolver on hls playlist', function () {
    $playlist = new DynamicHLSPlaylist;

    $playlist->setKeyUrlResolver(fn ($key) => "url1-{$key}");
    $playlist->setKeyUrlResolver(fn ($key) => "url2-{$key}");

    expect($playlist->getKeyUrlResolver())->not->toBeNull();
});

// DASH Manifest Tests
it('can set media url resolver on dash manifest', function () {
    $manifest = new DynamicDASHManifest;

    $resolver = fn ($filename) => Storage::disk('public')->url($filename);

    $result = $manifest->setMediaUrlResolver($resolver);

    expect($result)->toBe($manifest);
    expect($manifest->getMediaUrlResolver())->toBe($resolver);
});

it('can set init url resolver on dash manifest', function () {
    $manifest = new DynamicDASHManifest;

    $resolver = fn ($filename) => "https://cdn.example.com/init/{$filename}";

    $result = $manifest->setInitUrlResolver($resolver);

    expect($result)->toBe($manifest);
    expect($manifest->getInitUrlResolver())->toBe($resolver);
});

it('can chain resolver setters on dash manifest', function () {
    $manifest = new DynamicDASHManifest;

    $result = $manifest
        ->setMediaUrlResolver(fn ($file) => "media/{$file}")
        ->setInitUrlResolver(fn ($file) => "init/{$file}");

    expect($result)->toBe($manifest);
    expect($manifest->getMediaUrlResolver())->not->toBeNull();
    expect($manifest->getInitUrlResolver())->not->toBeNull();
});

it('clears cache when setting new resolver on dash manifest', function () {
    $manifest = new DynamicDASHManifest;

    $manifest->setMediaUrlResolver(fn ($file) => "url1-{$file}");
    $manifest->setMediaUrlResolver(fn ($file) => "url2-{$file}");

    expect($manifest->getMediaUrlResolver())->not->toBeNull();
});

it('processes dash mpd with BaseURL elements', function () {
    Storage::fake('local');
    Storage::disk('local')->put('manifest.mpd', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<MPD xmlns="urn:mpeg:dash:schema:mpd:2011">
  <Period>
    <AdaptationSet>
      <BaseURL>video/segment.m4s</BaseURL>
      <Representation id="0">
        <SegmentTemplate timescale="1" initialization="init.m4s" media="chunk-$Number$.m4s" startNumber="1">
          <SegmentTimeline>
            <S t="0" d="10"/>
          </SegmentTimeline>
        </SegmentTemplate>
      </Representation>
    </AdaptationSet>
  </Period>
</MPD>
XML
    );

    $manifest = new DynamicDASHManifest('local');
    $manifest->open('manifest.mpd')
        ->setMediaUrlResolver(fn ($file) => "https://cdn.example.com/media/{$file}")
        ->setInitUrlResolver(fn ($file) => "https://cdn.example.com/init/{$file}");

    $result = $manifest->get();

    // BaseURL is resolved via media resolver
    expect($result)->toContain('https://cdn.example.com/media/video/segment.m4s')
        // SegmentTemplate with $Number$ is expanded to SegmentList
        ->and($result)->not->toContain('SegmentTemplate')
        ->and($result)->toContain('SegmentList')
        // Init segment is resolved via init resolver
        ->and($result)->toContain('sourceURL="https://cdn.example.com/init/init.m4s"')
        // Segment URL is expanded and resolved ($Number$ replaced with 1)
        ->and($result)->toContain('media="https://cdn.example.com/media/chunk-1.m4s"');
});

it('processes dash mpd with SegmentTemplate concrete URLs', function () {
    Storage::fake('local');
    Storage::disk('local')->put('manifest.mpd', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<MPD xmlns="urn:mpeg:dash:schema:mpd:2011">
  <Period>
    <AdaptationSet>
      <SegmentTemplate initialization="init.m4s" media="chunk.m4s"/>
    </AdaptationSet>
  </Period>
</MPD>
XML
    );

    $manifest = new DynamicDASHManifest('local');
    $manifest->open('manifest.mpd')
        ->setMediaUrlResolver(fn ($file) => "https://cdn.example.com/{$file}")
        ->setInitUrlResolver(fn ($file) => "https://cdn.example.com/{$file}");

    $result = $manifest->get();

    // Concrete URLs without template variables are signed directly
    expect($result)->toContain('initialization="https://cdn.example.com/init.m4s"')
        ->and($result)->toContain('media="https://cdn.example.com/chunk.m4s"');
});

it('processes dash mpd with sourceURL attributes', function () {
    Storage::fake('local');
    Storage::disk('local')->put('manifest.mpd', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<MPD xmlns="urn:mpeg:dash:schema:mpd:2011">
  <Period>
    <AdaptationSet>
      <SegmentList>
        <Initialization sourceURL="init.m4s"/>
        <SegmentURL media="segment-1.m4s"/>
        <SegmentURL media="segment-2.m4s"/>
      </SegmentList>
    </AdaptationSet>
  </Period>
</MPD>
XML
    );

    $manifest = new DynamicDASHManifest('local');
    $manifest->open('manifest.mpd')
        ->setMediaUrlResolver(fn ($file) => "https://cdn.example.com/{$file}")
        ->setInitUrlResolver(fn ($file) => "https://cdn.example.com/{$file}");

    $result = $manifest->get();

    expect($result)->toContain('sourceURL="https://cdn.example.com/init.m4s"')
        ->and($result)->toContain('media="https://cdn.example.com/segment-1.m4s"')
        ->and($result)->toContain('media="https://cdn.example.com/segment-2.m4s"');
});

it('processes dash mpd without resolvers', function () {
    Storage::fake('local');
    $originalMpd = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<MPD xmlns="urn:mpeg:dash:schema:mpd:2011">
  <Period>
    <AdaptationSet>
      <BaseURL>segment.m4s</BaseURL>
      <SegmentTemplate initialization="init.m4s" media="chunk.m4s"/>
    </AdaptationSet>
  </Period>
</MPD>
XML;
    Storage::disk('local')->put('manifest.mpd', $originalMpd);

    $manifest = new DynamicDASHManifest('local');
    $manifest->open('manifest.mpd');

    $result = $manifest->get();

    // Without resolvers, the URLs should remain unchanged
    expect($result)->toContain('<BaseURL>segment.m4s</BaseURL>')
        ->and($result)->toContain('initialization="init.m4s"')
        ->and($result)->toContain('media="chunk.m4s"');
});

it('caches resolved urls in dash manifest', function () {
    Storage::fake('local');
    Storage::disk('local')->put('manifest.mpd', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<MPD xmlns="urn:mpeg:dash:schema:mpd:2011">
  <Period>
    <AdaptationSet>
      <Representation id="0">
        <SegmentTemplate timescale="1" initialization="init.m4s" media="chunk-$Number$.m4s" startNumber="1">
          <SegmentTimeline>
            <S t="0" d="10"/>
          </SegmentTimeline>
        </SegmentTemplate>
      </Representation>
      <Representation id="1">
        <SegmentTemplate timescale="1" initialization="init.m4s" media="chunk-$Number$.m4s" startNumber="1">
          <SegmentTimeline>
            <S t="0" d="10"/>
          </SegmentTimeline>
        </SegmentTemplate>
      </Representation>
    </AdaptationSet>
  </Period>
</MPD>
XML
    );

    $initCallCount = 0;
    $mediaCallCount = 0;

    $initResolver = function ($file) use (&$initCallCount) {
        $initCallCount++;

        return "https://cdn.example.com/init/{$file}";
    };

    $mediaResolver = function ($file) use (&$mediaCallCount) {
        $mediaCallCount++;

        return "https://cdn.example.com/media/{$file}";
    };

    $manifest = new DynamicDASHManifest('local');
    $manifest->open('manifest.mpd')
        ->setMediaUrlResolver($mediaResolver)
        ->setInitUrlResolver($initResolver);

    $manifest->get();

    // init.m4s appears twice but should only be resolved once due to caching
    // chunk-1.m4s appears twice but should only be resolved once due to caching
    expect($initCallCount)->toBe(1)
        ->and($mediaCallCount)->toBe(1);
});

it('can parse hls playlist lines', function () {
    $lines = "#EXTM3U\n#EXT-X-VERSION:3\nvideo.ts";

    $parsed = DynamicHLSPlaylist::parseLines($lines);

    expect($parsed)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($parsed->count())->toBe(3);
});

it('expands shaka packager SegmentTemplate with $Number$ into SegmentList', function () {
    Storage::fake('local');
    Storage::disk('local')->put('manifest.mpd', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!--Generated with https://github.com/shaka-project/shaka-packager version v3.4.2-c819dea-release-->
<MPD xmlns="urn:mpeg:dash:schema:mpd:2011" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="urn:mpeg:dash:schema:mpd:2011 DASH-MPD.xsd" profiles="urn:mpeg:dash:profile:isoff-live:2011" minBufferTime="PT2S" type="static" mediaPresentationDuration="PT10.066667S">
  <Period id="0">
    <AdaptationSet id="0" contentType="video" maxWidth="1920" maxHeight="1080" maxFrameRate="15360/256" segmentAlignment="true" par="16:9">
      <Representation id="0" bandwidth="102489" codecs="avc1.4d400c" mimeType="video/mp4" sar="1:1" width="256" height="144" frameRate="15360/512">
        <SegmentTemplate timescale="15360" initialization="video_144p_108k_h264_init.mp4" media="video_144p_108k_h264_$Number$.mp4" startNumber="1">
          <SegmentTimeline>
            <S t="0" d="154624"/>
          </SegmentTimeline>
        </SegmentTemplate>
      </Representation>
      <Representation id="1" bandwidth="226456" codecs="avc1.4d4015" mimeType="video/mp4" sar="1:1" width="426" height="240" frameRate="15360/512">
        <SegmentTemplate timescale="15360" initialization="video_240p_242k_h264_init.mp4" media="video_240p_242k_h264_$Number$.mp4" startNumber="1">
          <SegmentTimeline>
            <S t="0" d="154624"/>
          </SegmentTimeline>
        </SegmentTemplate>
      </Representation>
    </AdaptationSet>
  </Period>
</MPD>
XML
    );

    $manifest = new DynamicDASHManifest('local');
    $manifest->open('manifest.mpd')
        ->setMediaUrlResolver(fn ($file) => "https://s3.example.com/signed/{$file}?token=abc")
        ->setInitUrlResolver(fn ($file) => "https://s3.example.com/signed/{$file}?token=def");

    $result = $manifest->get();

    // SegmentTemplate should be replaced with SegmentList
    expect($result)->not->toContain('SegmentTemplate')
        ->and($result)->toContain('SegmentList')
        // Init segments should be signed
        ->and($result)->toContain('sourceURL="https://s3.example.com/signed/video_144p_108k_h264_init.mp4?token=def"')
        ->and($result)->toContain('sourceURL="https://s3.example.com/signed/video_240p_242k_h264_init.mp4?token=def"')
        // Media segments should have $Number$ expanded to 1 and signed
        ->and($result)->toContain('media="https://s3.example.com/signed/video_144p_108k_h264_1.mp4?token=abc"')
        ->and($result)->toContain('media="https://s3.example.com/signed/video_240p_242k_h264_1.mp4?token=abc"')
        // Template variables should not appear in output
        ->and($result)->not->toContain('$Number$');
});

it('expands SegmentTemplate with multiple segments from SegmentTimeline', function () {
    Storage::fake('local');
    Storage::disk('local')->put('manifest.mpd', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<MPD xmlns="urn:mpeg:dash:schema:mpd:2011">
  <Period>
    <AdaptationSet>
      <Representation id="0">
        <SegmentTemplate timescale="15360" initialization="init.mp4" media="seg_$Number$.mp4" startNumber="1">
          <SegmentTimeline>
            <S t="0" d="30720"/>
            <S d="30720" r="2"/>
            <S d="15360"/>
          </SegmentTimeline>
        </SegmentTemplate>
      </Representation>
    </AdaptationSet>
  </Period>
</MPD>
XML
    );

    $manifest = new DynamicDASHManifest('local');
    $manifest->open('manifest.mpd')
        ->setMediaUrlResolver(fn ($file) => "https://cdn.example.com/{$file}")
        ->setInitUrlResolver(fn ($file) => "https://cdn.example.com/{$file}");

    $result = $manifest->get();

    // 1 + (1 + r=2) + 1 = 5 segments total
    expect($result)->toContain('media="https://cdn.example.com/seg_1.mp4"')
        ->and($result)->toContain('media="https://cdn.example.com/seg_2.mp4"')
        ->and($result)->toContain('media="https://cdn.example.com/seg_3.mp4"')
        ->and($result)->toContain('media="https://cdn.example.com/seg_4.mp4"')
        ->and($result)->toContain('media="https://cdn.example.com/seg_5.mp4"')
        ->and($result)->not->toContain('$Number$')
        ->and($result)->not->toContain('SegmentTemplate');
});

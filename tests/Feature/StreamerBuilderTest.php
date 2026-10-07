<?php

declare(strict_types=1);

use Foxws\Media\Encoding\Ladder;
use Foxws\Media\Encoding\Rendition;
use Foxws\Media\Encoding\VideoCodec;
use Foxws\Media\Encryption\EncryptionKey;
use Foxws\Media\Encryption\ProtectionScheme;
use Foxws\Media\Events\ExportCompleted;
use Foxws\Media\Events\ExportFailed;
use Foxws\Media\Exceptions\InvalidMediaException;
use Foxws\Media\Exceptions\ProcessFailedException;
use Foxws\Media\Facades\Media;
use Foxws\Media\Testing\FakeProbe;
use Foxws\Media\Testing\MediaFake;
use Foxws\Streamer\Exceptions\StreamerException;
use Foxws\Streamer\StreamerBuilder;
use Foxws\Streamer\StreamerExecutable;
use Foxws\Streamer\Testing\FakeStreamer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('media');
    Storage::fake('streams');
    Storage::disk('media')->put('videos/clip.mp4', 'source');
    Storage::disk('media')->put('captions/clip.en.vtt', 'WEBVTT');

    $this->fake = FakeStreamer::respond(Media::fake());
});

function streamer(string ...$paths): StreamerBuilder
{
    return Media::fromDisk('media')->open(...($paths !== [] ? $paths : ['videos/clip.mp4']))->streamer();
}

/**
 * @return list<string>
 */
function lastStreamerRun(MediaFake $fake): array
{
    $commands = $fake->commands(StreamerExecutable::ShakaStreamer);

    return end($commands) ?: [];
}

it('describes the inputs and pipeline as shaka streamer configs', function (): void {
    $config = streamer()
        ->addVideoStream()
        ->addAudioStream(language: 'en')
        ->addTextStream('captions/clip.en.vtt', 'en')
        ->withResolutions('1080p', '720p')
        ->withHlsPlaylist()
        ->withDashManifest('dash/manifest.mpd')
        ->withTrickPlay()
        ->withOption('scene_detection', false)
        ->config();

    $localPath = fn (string $path): string => Media::fromDisk('media')->open($path)->mediaFor()->localPath();

    expect($config['input']['inputs'])->toBe([
        ['name' => $localPath('videos/clip.mp4'), 'input_type' => 'file', 'media_type' => 'video'],
        ['name' => $localPath('videos/clip.mp4'), 'input_type' => 'file', 'media_type' => 'audio', 'language' => 'en'],
        ['name' => $localPath('captions/clip.en.vtt'), 'input_type' => 'file', 'media_type' => 'text', 'language' => 'en'],
    ])->and($config['pipeline'])->toBe([
        'streaming_mode' => 'vod',
        'manifest_format' => ['dash', 'hls'],
        'dash_output' => 'dash/manifest.mpd',
        'hls_output' => 'master.m3u8',
        'video_codecs' => ['h264'],
        'audio_codecs' => ['aac'],
        'segment_size' => 6.0,
        'resolutions' => ['1080p', '720p'],
        'generate_iframe_playlist' => true,
        'scene_detection' => false,
    ]);
});

it('adds the streams every opened file contains', function (): void {
    Storage::disk('media')->put('videos/silent.mp4', 'source');
    $this->fake->probe('silent.mp4', FakeProbe::video(audio: false));

    $inputs = streamer('videos/clip.mp4', 'videos/silent.mp4')->addStreamsFrom()->withHlsPlaylist()->config()['input']['inputs'];

    expect(array_column($inputs, 'media_type'))->toBe(['video', 'audio', 'video']);
});

it('encodes the resolutions and codec of a ladder', function (): void {
    $pipeline = streamer()->addVideoStream()->withHlsPlaylist()
        ->withLadder(new Ladder([new Rendition(2160, 16000), new Rendition(720, 2800)], VideoCodec::Hevc))
        ->config()['pipeline'];

    expect($pipeline['resolutions'])->toBe(['4k', '720p'])
        ->and($pipeline['video_codecs'])->toBe(['hevc']);
});

it('rejects ladder heights shaka streamer has no resolution for', function (): void {
    streamer()->withLadder(new Ladder([new Rendition(900, 3500)]));
})->throws(InvalidArgumentException::class, 'Shaka Streamer has no resolution for a height of [900]');

it('applies the configured defaults', function (): void {
    config([
        'streamer.video_codecs' => 'hw:h264, vp9',
        'streamer.audio_codecs' => 'opus',
        'streamer.segment_duration' => '4',
        'streamer.hardware_acceleration' => 'vaapi',
        'streamer.ffmpeg_input_args' => '-hwaccel vaapi',
    ]);

    $config = streamer()->addVideoStream()->addAudioStream(options: ['extra_input_args' => '-ss 10'])->withHlsPlaylist()->config();

    expect($config['pipeline'])->toMatchArray([
        'video_codecs' => ['hw:h264', 'vp9'],
        'audio_codecs' => ['opus'],
        'segment_size' => 4.0,
        'hwaccel_api' => 'vaapi',
    ])->and(array_column($config['input']['inputs'], 'extra_input_args'))->toBe(['-hwaccel vaapi', '-ss 10']);
});

it('runs shaka streamer and saves the manifests first to the target disk', function (): void {
    Event::fake([ExportCompleted::class]);

    $result = streamer()
        ->addVideoStream()
        ->addAudioStream()
        ->withResolutions('720p', '480p')
        ->withHlsPlaylist()
        ->withDashManifest()
        ->toDisk('streams')
        ->withContext(['video' => 1])
        ->save('clip');

    $arguments = lastStreamerRun($this->fake);

    expect($result->paths())->toBe(['clip/master.m3u8', 'clip/manifest.mpd', 'clip/video_480p.mp4', 'clip/video_720p.mp4'])
        ->and($result->disk()->name())->toBe('streams')
        ->and([$arguments[0], $arguments[2], $arguments[4]])->toBe(['-i', '-p', '-o'])
        ->and(file_exists($arguments[1]))->toBeFalse();
    Storage::disk('streams')->assertExists('clip/master.m3u8');
    Event::assertDispatched(ExportCompleted::class, fn (ExportCompleted $event): bool => $event->context === ['video' => 1]);
});

it('encrypts with a raw key and saves the key file next to the segments', function (): void {
    $key = new EncryptionKey('0123456789abcdef0123456789abcdef', 'fedcba9876543210fedcba9876543210');

    $builder = streamer()->addVideoStream()->withHlsPlaylist()->withEncryption($key, ProtectionScheme::Cbcs, label: 'SD', clearLead: 2.5);

    expect($builder->config()['pipeline']['encryption'])->toBe([
        'enable' => true,
        'encryption_mode' => 'raw',
        'keys' => [['label' => 'SD', 'key_id' => 'fedcba9876543210fedcba9876543210', 'key' => '0123456789abcdef0123456789abcdef']],
        'protection_scheme' => 'cbcs',
        'clear_lead' => 2.5,
        'hls_key_uri' => 'key',
    ]);

    $result = $builder->save('clip');

    expect($result->encryptionKey())->toBe($key)
        ->and(Storage::disk('media')->get('clip/key'))->toBe($key->binary());
});

it('generates a key and rejects schemes shaka streamer does not support', function (): void {
    expect(streamer()->withEncryption()->encryptionKey())->toBeInstanceOf(EncryptionKey::class);

    streamer()->withEncryption(scheme: ProtectionScheme::Cens);
})->throws(InvalidArgumentException::class, 'Shaka Streamer only encrypts with cenc or cbcs, not [cens].');

it('runs the binaries on the PATH when asked', function (): void {
    $command = streamer()->addVideoStream()->withHlsPlaylist()->useSystemBinaries()->command();

    expect($command)->toEndWith('-i config/input.yaml -p config/pipeline.yaml -o output --use-system-binaries');
});

it('fails without streams or manifests', function (Closure $build, string $exception): void {
    expect(fn (): array => $build()->config())->toThrow($exception);
})->with([
    'no streams' => [fn (): StreamerBuilder => streamer()->withHlsPlaylist(), InvalidMediaException::class],
    'no manifests' => [fn (): StreamerBuilder => streamer()->addVideoStream(), StreamerException::class],
]);

it('fails when shaka streamer writes no manifest', function (): void {
    $this->fake->respondUsing(StreamerExecutable::ShakaStreamer, fn (array $arguments): string => '');
    Event::fake([ExportFailed::class]);

    expect(fn () => streamer()->addVideoStream()->withHlsPlaylist()->save('clip'))
        ->toThrow(StreamerException::class, 'Shaka Streamer finished without writing [master.m3u8].');
    Event::assertDispatched(ExportFailed::class);
});

it('throws laravel-media process failures', function (): void {
    $this->fake->failNext(StreamerExecutable::ShakaStreamer, 'RuntimeError: ffmpeg failed');

    streamer()->addVideoStream()->withHlsPlaylist()->save('clip');
})->throws(ProcessFailedException::class);

it('rejects empty values', function (Closure $configure, string $message): void {
    expect(fn (): StreamerBuilder => $configure(streamer()))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'resolutions' => [fn (StreamerBuilder $builder): StreamerBuilder => $builder->withResolutions(), 'at least one resolution'],
    'codecs' => [fn (StreamerBuilder $builder): StreamerBuilder => $builder->withVideoCodecs(' '), 'at least one video codec'],
    'segment duration' => [fn (StreamerBuilder $builder): StreamerBuilder => $builder->segmentDuration(0), 'positive number of seconds'],
]);

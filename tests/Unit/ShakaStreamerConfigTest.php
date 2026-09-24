<?php

declare(strict_types=1);

use Foxws\Streamer\Exceptions\InvalidStreamConfigurationException;
use Foxws\Streamer\Filesystem\TemporaryDirectories;
use Foxws\Streamer\Support\CommandBuilder;
use Foxws\Streamer\Support\ProtectionScheme;
use Foxws\Streamer\Support\ShakaStreamer;
use Foxws\Streamer\Support\Streamer;

it('adds video and audio from the same file as separate inputs', function () {
    $config = CommandBuilder::make()
        ->addVideoStream('/in/clip.mp4', 'video.mp4')
        ->addAudioStream('/in/clip.mp4', 'audio.mp4', ['language' => 'nl'])
        ->addTextStream('/in/clip.vtt', 'subtitles.mp4', ['language' => 'nl'])
        ->build();

    expect($config['input_config']['inputs'])->toBe([
        ['input_type' => 'file', 'name' => '/in/clip.mp4', 'media_type' => 'video'],
        ['input_type' => 'file', 'name' => '/in/clip.mp4', 'media_type' => 'audio', 'language' => 'nl'],
        ['input_type' => 'file', 'name' => '/in/clip.vtt', 'media_type' => 'text', 'language' => 'nl'],
    ]);
});

it('skips inputs that are exact duplicates', function () {
    $config = CommandBuilder::make()
        ->addVideoStream('/in/clip.mp4', 'video.mp4')
        ->addVideoStream('/in/clip.mp4', 'video-copy.mp4')
        ->build();

    expect($config['input_config']['inputs'])->toHaveCount(1);
});

it('adds extra input args to every input instead of the pipeline', function () {
    $config = CommandBuilder::make()
        ->withExtraInputArgs('-re')
        ->addVideoStream('/in/clip.mp4', 'video.mp4')
        ->addAudioStream('/in/clip.mp4', 'audio.mp4', ['extra_input_args' => '-ss 5'])
        ->build();

    expect($config['pipeline_config'])->not->toHaveKey('extra_input_args')
        ->and(array_column($config['input_config']['inputs'], 'extra_input_args'))->toBe(['-re', '-ss 5']);
});

it('passes channel layouts as a list', function () {
    $config = CommandBuilder::make()
        ->addAudioStream('/in/clip.mp4', 'audio.mp4')
        ->withChannelLayouts('stereo, surround')
        ->build();

    expect($config['pipeline_config']['channel_layouts'])->toBe(['stereo', 'surround']);
});

it('rejects protection schemes Shaka Streamer does not support', function (string $scheme) {
    CommandBuilder::make()->withEncryption(['enable' => true, 'protection_scheme' => $scheme]);
})->with(['cbc1', 'cens'])->throws(InvalidStreamConfigurationException::class, 'cenc');

it('rejects key rotation in the encryption config', function () {
    CommandBuilder::make()->withEncryption(['enable' => true, 'crypto_period_duration' => 60]);
})->throws(InvalidStreamConfigurationException::class, 'key rotation');

it('accepts a protection scheme enum in the encryption config', function () {
    $config = CommandBuilder::make()
        ->addVideoStream('/in/clip.mp4', 'video.mp4')
        ->withEncryption(['enable' => true, 'protection_scheme' => ProtectionScheme::Cbcs])
        ->build();

    expect($config['pipeline_config']['encryption']['protection_scheme'])->toBe('cbcs');
});

describe('AES encryption', function () {
    beforeEach(function () {
        $this->tempDirs = new TemporaryDirectories(sys_get_temp_dir().'/test-streamer-config');
        app()->instance(TemporaryDirectories::class, $this->tempDirs);
    });

    afterEach(function () {
        $this->tempDirs->deleteAll();
    });

    it('points the HLS key URI at the key file', function () {
        $streamer = new Streamer(new ShakaStreamer);
        $streamer->addVideoStream('/in/clip.mp4', 'video.mp4');

        $key = $streamer->withAESEncryption('clip.key', ProtectionScheme::Cbcs);

        $encryption = $streamer->getBuilder()->getOptions()->get('encryption');

        expect($encryption)->toMatchArray([
            'hls_key_uri' => 'clip.key',
            'protection_scheme' => 'cbcs',
            'clear_lead' => 0,
        ])
            ->and($encryption['keys'][0]['key'])->toBe($key->key)
            ->and(basename($key->filePath))->toBe('clip.key');
    });

    it('fails right away when key rotation is requested', function () {
        (new Streamer(new ShakaStreamer))->withKeyRotationDuration(60);
    })->throws(InvalidStreamConfigurationException::class, 'key rotation');
});

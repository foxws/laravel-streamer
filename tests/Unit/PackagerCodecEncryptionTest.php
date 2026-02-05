<?php

declare(strict_types=1);

use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\Filesystem\MediaCollection;
use Foxws\Streamer\Filesystem\TemporaryDirectories;
use Foxws\Streamer\Support\Packager;
use Foxws\Streamer\Support\ShakaPackager;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('export');

    // Create test video files for different codecs using fixtures if available
    if (file_exists(fixture('sample_h264.mp4'))) {
        Storage::disk('local')->put('video_h264.mp4', file_get_contents(fixture('sample_h264.mp4')));
    } else {
        Storage::disk('local')->put('video_h264.mp4', 'fake h264 video content');
    }

    if (file_exists(fixture('sample_hevc.mp4'))) {
        Storage::disk('local')->put('video_hevc.mp4', file_get_contents(fixture('sample_hevc.mp4')));
    } else {
        Storage::disk('local')->put('video_hevc.mp4', 'fake hevc video content');
    }

    if (file_exists(fixture('sample_av1.mp4'))) {
        Storage::disk('local')->put('video_av1.mp4', file_get_contents(fixture('sample_av1.mp4')));
    } else {
        Storage::disk('local')->put('video_av1.mp4', 'fake av1 video content');
    }
});

it('supports AES encryption with H.264 codec', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'video_h264.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    // Add H.264 video stream with encryption
    $keyData = $packager->withAESEncryption('h264.key', 'cbc1', 'h264');

    $packager->addStream([
        'in' => $media->getLocalPath(),
        'stream' => 'video',
        'output' => 'h264_encrypted.mp4',
    ]);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    // Verify encryption is configured
    expect($options)->toHaveKey('protection_scheme')
        ->and($options['protection_scheme'])->toBe('cbc1')
        ->and($options)->toHaveKey('enable_raw_key_encryption')
        ->and($options['enable_raw_key_encryption'])->toBeTrue()
        ->and($options)->toHaveKey('hls_key_uri')
        ->and($options['hls_key_uri'])->toBe('h264.key')
        ->and($keyData['file_path'])->toContain('h264.key');

    // Cleanup
    $tempDirs->deleteAll();
});

it('supports AES encryption with HEVC/H.265 codec', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'video_hevc.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    // Add HEVC video stream with encryption
    $keyData = $packager->withAESEncryption('hevc.key', 'cbc1', 'hevc');

    $packager->addStream([
        'in' => $media->getLocalPath(),
        'stream' => 'video',
        'output' => 'hevc_encrypted.mp4',
    ]);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    // Verify encryption is configured
    expect($options)->toHaveKey('protection_scheme')
        ->and($options['protection_scheme'])->toBe('cbc1')
        ->and($options)->toHaveKey('enable_raw_key_encryption')
        ->and($options['enable_raw_key_encryption'])->toBeTrue()
        ->and($options)->toHaveKey('hls_key_uri')
        ->and($options['hls_key_uri'])->toBe('hevc.key')
        ->and($keyData['file_path'])->toContain('hevc.key');

    // Cleanup
    $tempDirs->deleteAll();
});

it('supports AES encryption with AV1 codec', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'video_av1.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    // Add AV1 video stream with encryption
    $keyData = $packager->withAESEncryption('av1.key', 'cbc1', 'av1');

    $packager->addStream([
        'in' => $media->getLocalPath(),
        'stream' => 'video',
        'output' => 'av1_encrypted.mp4',
    ]);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    // Verify encryption is configured
    expect($options)->toHaveKey('protection_scheme')
        ->and($options['protection_scheme'])->toBe('cbc1')
        ->and($options)->toHaveKey('enable_raw_key_encryption')
        ->and($options['enable_raw_key_encryption'])->toBeTrue()
        ->and($options)->toHaveKey('hls_key_uri')
        ->and($options['hls_key_uri'])->toBe('av1.key')
        ->and($keyData['file_path'])->toContain('av1.key');

    // Cleanup
    $tempDirs->deleteAll();
});

it('supports cbcs protection scheme with H.264', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'video_h264.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    // Use cbcs (for newer devices/platforms)
    $keyData = $packager->withAESEncryption('h264_cbcs.key', 'cbcs', 'h264');

    $packager->addStream([
        'in' => $media->getLocalPath(),
        'stream' => 'video',
        'output' => 'h264_cbcs.mp4',
    ]);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options['protection_scheme'])->toBe('cbcs');

    // Cleanup
    $tempDirs->deleteAll();
});

it('supports cenc protection scheme with HEVC', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'video_hevc.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    // Use cenc (Common Encryption)
    $keyData = $packager->withAESEncryption('hevc_cenc.key', 'cenc', 'hevc');

    $packager->addStream([
        'in' => $media->getLocalPath(),
        'stream' => 'video',
        'output' => 'hevc_cenc.mp4',
    ]);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options['protection_scheme'])->toBe('cenc');

    // Cleanup
    $tempDirs->deleteAll();
});

it('supports SAMPLE-AES with AV1 for HLS', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'video_av1.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    // Use null protection scheme for SAMPLE-AES (HLS-specific)
    $keyData = $packager->withAESEncryption('av1_sample_aes.key', null, 'av1');

    $packager->addStream([
        'in' => $media->getLocalPath(),
        'stream' => 'video',
        'output' => 'av1_sample_aes.m3u8',
    ]);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    // SAMPLE-AES doesn't use protection_scheme
    expect($options)->not->toHaveKey('protection_scheme')
        ->and($options)->toHaveKey('enable_raw_key_encryption')
        ->and($options['enable_raw_key_encryption'])->toBeTrue();

    // Cleanup
    $tempDirs->deleteAll();
});

it('encrypts multiple codec streams in single packaging operation', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    // Multiple codec inputs
    $mediaH264 = Media::make('local', 'video_h264.mp4');
    $mediaHevc = Media::make('local', 'video_hevc.mp4');
    $mediaAv1 = Media::make('local', 'video_av1.mp4');

    $collection = MediaCollection::make([$mediaH264, $mediaHevc, $mediaAv1]);

    $packager->open($collection);

    // Configure encryption (applies to all streams)
    $keyData = $packager->withAESEncryption('master.key', 'cbc1', 'multi');

    // Add streams for each codec
    $packager->addStream([
        'in' => $mediaH264->getLocalPath(),
        'stream' => 'video',
        'output' => 'h264_1080p.mp4',
    ]);

    $packager->addStream([
        'in' => $mediaHevc->getLocalPath(),
        'stream' => 'video',
        'output' => 'hevc_1080p.mp4',
    ]);

    $packager->addStream([
        'in' => $mediaAv1->getLocalPath(),
        'stream' => 'video',
        'output' => 'av1_1080p.mp4',
    ]);

    $builder = $packager->getBuilder();
    $streams = $builder->getStreams();
    $options = $builder->getOptions();

    // Verify all streams are added
    expect($streams)->toHaveCount(3)
        ->and($options['protection_scheme'])->toBe('cbc1')
        ->and($options)->toHaveKey('enable_raw_key_encryption')
        ->and($options['hls_key_uri'])->toBe('master.key');

    // Cleanup
    $tempDirs->deleteAll();
});

it('generates unique encryption keys per codec when needed', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    // Test H.264
    $packagerH264 = new Packager($driver, $logger);
    $mediaH264 = Media::make('local', 'video_h264.mp4');
    $packagerH264->open(MediaCollection::make([$mediaH264]));
    $keyDataH264 = $packagerH264->withAESEncryption('h264_unique.key', 'cbc1', 'h264');

    // Test HEVC
    $packagerHevc = new Packager($driver, $logger);
    $mediaHevc = Media::make('local', 'video_hevc.mp4');
    $packagerHevc->open(MediaCollection::make([$mediaHevc]));
    $keyDataHevc = $packagerHevc->withAESEncryption('hevc_unique.key', 'cbc1', 'hevc');

    // Test AV1
    $packagerAv1 = new Packager($driver, $logger);
    $mediaAv1 = Media::make('local', 'video_av1.mp4');
    $packagerAv1->open(MediaCollection::make([$mediaAv1]));
    $keyDataAv1 = $packagerAv1->withAESEncryption('av1_unique.key', 'cbc1', 'av1');

    // Verify each got unique keys
    expect($keyDataH264['key'])->not->toBe($keyDataHevc['key'])
        ->and($keyDataH264['key'])->not->toBe($keyDataAv1['key'])
        ->and($keyDataHevc['key'])->not->toBe($keyDataAv1['key'])
        ->and($keyDataH264['key_id'])->not->toBe($keyDataHevc['key_id'])
        ->and($keyDataH264['key_id'])->not->toBe($keyDataAv1['key_id'])
        ->and($keyDataHevc['key_id'])->not->toBe($keyDataAv1['key_id']);

    // Cleanup
    $tempDirs->deleteAll();
});

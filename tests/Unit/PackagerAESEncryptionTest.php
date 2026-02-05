<?php

declare(strict_types=1);

use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\Filesystem\MediaCollection;
use Foxws\Streamer\Filesystem\TemporaryDirectories;
use Foxws\Streamer\Support\Packager;
use Foxws\Streamer\Support\PackagerResult;
use Foxws\Streamer\Support\ShakaPackager;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('export');

    // Create a test video file
    Storage::disk('local')->put('test.mp4', 'fake video content');
});

it('generates AES encryption with default settings', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    $keyData = $packager->withAESEncryption();

    // Verify key data structure
    expect($keyData)->toBeArray()
        ->toHaveKeys(['key', 'key_id', 'file_path'])
        ->and(strlen($keyData['key']))->toBe(32)
        ->and(strlen($keyData['key_id']))->toBe(32)
        ->and(file_exists($keyData['file_path']))->toBeTrue();

    // Cleanup
    $tempDirs->deleteAll();
});

it('configures encryption without protection scheme by default', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);
    $packager->withAESEncryption();

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options)->not->toHaveKey('protection_scheme')
        ->and($options)->toHaveKey('enable_raw_key_encryption')
        ->and($options['enable_raw_key_encryption'])->toBeTrue()
        ->and($options)->toHaveKey('clear_lead')
        ->and($options['clear_lead'])->toBe(0)
        ->and($options)->toHaveKey('hls_key_uri')
        ->and($options['hls_key_uri'])->toBe('key');

    // Cleanup
    $tempDirs->deleteAll();
});

it('allows custom protection scheme', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);
    $packager->withAESEncryption('encryption.key', 'cbcs');

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options['protection_scheme'])->toBe('cbcs');

    // Cleanup
    $tempDirs->deleteAll();
});

it('allows omitting protection scheme', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);
    $packager->withAESEncryption('encryption.key', null);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options)->not->toHaveKey('protection_scheme');

    // Cleanup
    $tempDirs->deleteAll();
});

it('supports custom key filename', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    $keyData = $packager->withAESEncryption('custom-key.bin');

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options['hls_key_uri'])->toBe('custom-key.bin')
        ->and($keyData['file_path'])->toContain('custom-key.bin');

    // Cleanup
    $tempDirs->deleteAll();
});

it('supports key rotation with crypto_period_duration', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);
    $packager->withAESEncryption();
    $packager->withKeyRotationDuration(300);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options)->toHaveKey('crypto_period_duration')
        ->and($options['crypto_period_duration'])->toBe(300);

    // Cleanup
    $tempDirs->deleteAll();
});

it('supports different key rotation intervals', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);
    $packager->withAESEncryption('rotation.key', 'cbc1');
    $packager->withKeyRotationDuration(1800); // 30 minutes

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options['crypto_period_duration'])->toBe(1800)
        ->and($options['protection_scheme'])->toBe('cbc1')
        ->and($options['hls_key_uri'])->toBe('rotation.key');

    // Cleanup
    $tempDirs->deleteAll();
});

it('collects all encryption keys after packaging with rotation', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    // Create a temporary directory with multiple key files (simulating rotation)
    $tempDir = sys_get_temp_dir().'/test-rotation-'.bin2hex(random_bytes(4));
    mkdir($tempDir, 0777, true);

    // Simulate multiple keys generated during rotation
    file_put_contents($tempDir.'/encryption_0.key', random_bytes(16));
    file_put_contents($tempDir.'/encryption_1.key', random_bytes(16));
    file_put_contents($tempDir.'/encryption_2.key', random_bytes(16));
    file_put_contents($tempDir.'/video.m4s', 'segment data'); // Non-key file

    $result = new PackagerResult('success', null, $tempDir);

    $keys = $result->getEncryptionKeys();

    expect($keys)->toHaveCount(3)
        ->and($keys[0])->toHaveKeys(['path', 'filename', 'content'])
        ->and($keys[0]['filename'])->toBe('encryption_0.key')
        ->and(strlen($keys[0]['content']))->toBe(32) // 16 bytes = 32 hex chars
        ->and($keys[1]['filename'])->toBe('encryption_1.key')
        ->and($keys[2]['filename'])->toBe('encryption_2.key');

    // Cleanup
    array_map('unlink', glob($tempDir.'/*'));
    rmdir($tempDir);
    $tempDirs->deleteAll();
});

it('tracks uploaded encryption keys during toDisk', function () {
    Storage::fake('s3');

    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    // Create temporary directory with encryption keys
    $tempDir = sys_get_temp_dir().'/test-upload-'.bin2hex(random_bytes(4));
    mkdir($tempDir, 0777, true);

    file_put_contents($tempDir.'/encryption_0.key', random_bytes(16));
    file_put_contents($tempDir.'/encryption_1.key', random_bytes(16));
    file_put_contents($tempDir.'/video.mp4', 'video data');

    $result = new PackagerResult('success', null, $tempDir);

    // Upload to S3
    $result->toDisk('s3', 'public', false);

    // Get uploaded keys
    $uploadedKeys = $result->getUploadedEncryptionKeys();

    expect($uploadedKeys)->toHaveCount(2)
        ->and($uploadedKeys[0])->toHaveKeys(['filename', 'path', 'content'])
        ->and($uploadedKeys[0]['filename'])->toBe('encryption_0.key')
        ->and($uploadedKeys[0]['path'])->toBe('encryption_0.key')
        ->and(strlen($uploadedKeys[0]['content']))->toBe(32)
        ->and($uploadedKeys[1]['filename'])->toBe('encryption_1.key');

    // Verify files were uploaded
    expect(Storage::disk('s3')->exists('encryption_0.key'))->toBeTrue()
        ->and(Storage::disk('s3')->exists('encryption_1.key'))->toBeTrue()
        ->and(Storage::disk('s3')->exists('video.mp4'))->toBeTrue();

    // Cleanup
    array_map('unlink', glob($tempDir.'/*'));
    rmdir($tempDir);
    $tempDirs->deleteAll();
});

it('configures clearkey encryption for DASH', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    // Configure clearkey encryption manually
    $packager->withEncryption([
        'enable_raw_key_encryption' => true,
        'keys' => 'label=:key_id=abba271e8bcf552bbd2e86a434a9a5d9:key=69eaa802a6763af979e8d1940fb88392',
        'protection_scheme' => 'cenc',
        'clear_lead' => 0,
    ]);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options)->toHaveKey('enable_raw_key_encryption')
        ->and($options['enable_raw_key_encryption'])->toBeTrue()
        ->and($options)->toHaveKey('keys')
        ->and($options)->toHaveKey('protection_scheme')
        ->and($options['protection_scheme'])->toBe('cenc')
        ->and($options)->toHaveKey('clear_lead')
        ->and($options['clear_lead'])->toBe(0);

    // Cleanup
    $tempDirs->deleteAll();
});

it('configures clearkey encryption with cbcs protection scheme', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    // Configure clearkey with cbcs (for FairPlay compatibility)
    $packager->withEncryption([
        'enable_raw_key_encryption' => true,
        'keys' => 'label=SD:key_id=abba271e8bcf552bbd2e86a434a9a5d9:key=69eaa802a6763af979e8d1940fb88392',
        'protection_scheme' => 'cbcs',
        'clear_lead' => 5,
    ]);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options['protection_scheme'])->toBe('cbcs')
        ->and($options['clear_lead'])->toBe(5)
        ->and($options)->toHaveKey('enable_raw_key_encryption');

    // Cleanup
    $tempDirs->deleteAll();
});

it('configures clearkey encryption with multiple keys', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $driver = mock(ShakaPackager::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $packager = new Packager($driver, $logger);

    $media = Media::make('local', 'test.mp4');
    $collection = MediaCollection::make([$media]);

    $packager->open($collection);

    // Configure clearkey with multiple keys (for different quality levels)
    $keys = 'label=SD:key_id=abba271e8bcf552bbd2e86a434a9a5d9:key=69eaa802a6763af979e8d1940fb88392,'.
            'label=HD:key_id=6d76f25cb17f5e16b8eaef6bbf582d8e:key=cb541084c99731aef4fff74500c12ead,'.
            'label=UHD1:key_id=3b8f3c56c8edf33d8152674ea3e1d6e1:key=5a5e9e9e2e6c1c9e2f6a8e5e1e1e9e2e';

    $packager->withEncryption([
        'enable_raw_key_encryption' => true,
        'keys' => $keys,
        'protection_scheme' => 'cenc',
    ]);

    $builder = $packager->getBuilder();
    $options = $builder->getOptions();

    expect($options)->toHaveKey('keys')
        ->and($options['keys'])->toContain('label=SD')
        ->and($options['keys'])->toContain('label=HD')
        ->and($options['keys'])->toContain('label=UHD1');

    // Cleanup
    $tempDirs->deleteAll();
});

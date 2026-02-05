<?php

declare(strict_types=1);

use Foxws\Streamer\Filesystem\TemporaryDirectories;
use Foxws\Streamer\Support\EncryptionKeyGenerator;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('test');
});

it('generates a 128-bit encryption key', function () {
    $key = EncryptionKeyGenerator::generateKey();

    expect($key)->toBeString()
        ->and(strlen($key))->toBe(32) // 16 bytes = 32 hex characters
        ->and(ctype_xdigit($key))->toBeTrue();
});

it('generates a 128-bit key ID', function () {
    $keyId = EncryptionKeyGenerator::generateKeyId();

    expect($keyId)->toBeString()
        ->and(strlen($keyId))->toBe(32)
        ->and(ctype_xdigit($keyId))->toBeTrue();
});

it('generates both key and key ID', function () {
    $data = EncryptionKeyGenerator::generate();

    expect($data)->toBeArray()
        ->toHaveKeys(['key_id', 'key'])
        ->and($data['key'])->toBeString()
        ->and($data['key_id'])->toBeString()
        ->and(strlen($data['key']))->toBe(32)
        ->and(strlen($data['key_id']))->toBe(32);
});

it('formats encryption config for Shaka Packager', function () {
    $formatted = EncryptionKeyGenerator::formatForShaka('keyid123', 'key456', 'test');

    expect($formatted)->toBe('label=test:key_id=keyid123:key=key456');
});

it('formats encryption config without label', function () {
    $formatted = EncryptionKeyGenerator::formatForShaka('keyid123', 'key456');

    expect($formatted)->toBe('label=:key_id=keyid123:key=key456');
});

it('writes key file to disk', function () {
    $key = EncryptionKeyGenerator::generateKey();

    EncryptionKeyGenerator::writeKeyFile('test', 'encryption.key', $key);

    expect(Storage::disk('test')->exists('encryption.key'))->toBeTrue();

    $content = Storage::disk('test')->get('encryption.key');
    expect($content)->toBe(hex2bin($key));
});

it('writes key to temporary storage when cache is available', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $key = EncryptionKeyGenerator::generateKey();
    $filePath = EncryptionKeyGenerator::writeKeyToTemporary($key, 'test.key');

    expect($filePath)->toStartWith(sys_get_temp_dir().'/test-cache/')
        ->and(file_exists($filePath))->toBeTrue()
        ->and(file_get_contents($filePath))->toBe(hex2bin($key));

    // Cleanup
    $tempDirs->deleteAll();
});

it('writes key to regular temp when cache is not available', function () {
    $tempDirs = new TemporaryDirectories(sys_get_temp_dir().'/test-temp');

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $key = EncryptionKeyGenerator::generateKey();
    $filePath = EncryptionKeyGenerator::writeKeyToTemporary($key, 'test.key');

    expect($filePath)->toStartWith(sys_get_temp_dir().'/test-temp/')
        ->and(file_exists($filePath))->toBeTrue();

    // Cleanup
    $tempDirs->deleteAll();
});

it('generates and writes key in one call', function () {
    $tempDirs = new TemporaryDirectories(
        sys_get_temp_dir().'/test-temp',
        sys_get_temp_dir().'/test-cache'
    );

    app()->instance(TemporaryDirectories::class, $tempDirs);

    $keyData = EncryptionKeyGenerator::generateAndWrite('my-key.key');

    expect($keyData)->toBeArray()
        ->toHaveKeys(['key', 'key_id', 'file_path'])
        ->and($keyData['key'])->toBeString()
        ->and($keyData['key_id'])->toBeString()
        ->and($keyData['file_path'])->toBeString()
        ->and(file_exists($keyData['file_path']))->toBeTrue();

    // Verify file content
    $fileContent = file_get_contents($keyData['file_path']);
    expect($fileContent)->toBe(hex2bin($keyData['key']));

    // Cleanup
    $tempDirs->deleteAll();
});

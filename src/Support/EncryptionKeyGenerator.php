<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Filesystem\TemporaryDirectories;
use Illuminate\Support\Facades\Storage;

class EncryptionKeyGenerator
{
    /**
     * Generate a 128-bit (16 bytes) encryption key for HLS
     */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate a 128-bit (16 bytes) key ID for HLS
     */
    public static function generateKeyId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate both key and key ID
     */
    public static function generate(): EncryptionKey
    {
        return EncryptionKey::generate();
    }

    /**
     * Format encryption config for Shaka Streamer
     */
    public static function formatForShaka(string $keyId, string $key, ?string $label = null): string
    {
        return sprintf('label=%s:key_id=%s:key=%s', $label ?? '', $keyId, $key);
    }

    /**
     * Write encryption key to disk
     */
    public static function writeKeyFile(string $disk, string $path, string $key): void
    {
        Storage::disk($disk)->put($path, hex2bin($key));
    }

    /**
     * Write encryption key to cache storage (RAM disk if available).
     * Returns the full path to the written key file.
     */
    public static function writeKeyToTemporary(string $key, string $filename = 'encryption.key'): string
    {
        $tempDirs = app(TemporaryDirectories::class);

        $directory = $tempDirs->hasCacheStorage()
            ? $tempDirs->createCache()
            : $tempDirs->create();

        $filePath = $directory.DIRECTORY_SEPARATOR.$filename;
        file_put_contents($filePath, hex2bin($key));

        return $filePath;
    }

    /**
     * Generate encryption key and write it to cache storage.
     */
    public static function generateAndWrite(string $filename = 'encryption.key'): EncryptionKey
    {
        return EncryptionKey::generateAndWrite($filename);
    }
}

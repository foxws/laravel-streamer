<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

class MediaHelper
{
    /**
     * Calculate appropriate bitrates based on resolution
     */
    public static function suggestedBitrates(int $width, int $height): array
    {
        $resolution = $width * $height;

        return match (true) {
            $resolution >= 3840 * 2160 => ['video' => '15000000', 'audio' => '192000'], // 4K
            $resolution >= 2560 * 1440 => ['video' => '8000000', 'audio' => '192000'],  // 1440p
            $resolution >= 1920 * 1080 => ['video' => '5000000', 'audio' => '128000'],  // 1080p
            $resolution >= 1280 * 720 => ['video' => '3000000', 'audio' => '128000'],   // 720p
            $resolution >= 854 * 480 => ['video' => '1500000', 'audio' => '96000'],     // 480p
            default => ['video' => '800000', 'audio' => '64000'],                       // 360p
        };
    }

    /**
     * Generate standard adaptive bitrate ladder
     */
    public static function standardABRLadder(): array
    {
        return [
            ['resolution' => '1920x1080', 'bandwidth' => '5000000', 'name' => '1080p'],
            ['resolution' => '1280x720', 'bandwidth' => '3000000', 'name' => '720p'],
            ['resolution' => '854x480', 'bandwidth' => '1500000', 'name' => '480p'],
            ['resolution' => '640x360', 'bandwidth' => '800000', 'name' => '360p'],
        ];
    }

    /**
     * Format file size in human-readable format
     */
    public static function formatFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Generate a random encryption key
     */
    public static function generateEncryptionKey(int $length = 16): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate a random key ID
     */
    public static function generateKeyId(int $length = 16): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Validate media file extension
     */
    public static function isSupportedFormat(string $filename): bool
    {
        $supportedExtensions = [
            'mp4', 'm4v', 'm4a',
            'mov', 'avi', 'mkv',
            'webm', 'flv', 'wmv',
            'ts', 'm2ts', 'mts',
            'mp3', 'aac', 'wav',
        ];

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, $supportedExtensions, true);
    }

    /**
     * Calculate estimated processing time based on file size
     *
     * @param  int  $fileSize  File size in bytes
     * @return int Estimated seconds
     */
    public static function estimateProcessingTime(int $fileSize): int
    {
        // Rough estimate: 1 GB takes ~60 seconds on average hardware
        $gbSize = $fileSize / 1024 / 1024 / 1024;

        return (int) ceil($gbSize * 60);
    }

    /**
     * Sanitize filename for safe usage
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove any character that isn't alphanumeric, dash, underscore, or dot
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove multiple consecutive underscores
        $sanitized = preg_replace('/_+/', '_', $sanitized);

        // Remove leading/trailing underscores
        return trim($sanitized, '_');
    }

    /**
     * Parse bandwidth string to integer
     */
    public static function parseBandwidth(string|int $bandwidth): int
    {
        if (is_int($bandwidth)) {
            return $bandwidth;
        }

        // Handle strings like "5M", "3.5M", "1500k"
        if (preg_match('/^([\d.]+)([kmg])?$/i', $bandwidth, $matches)) {
            $value = (float) $matches[1];
            $unit = strtolower($matches[2] ?? '');

            return (int) match ($unit) {
                'k' => $value * 1000,
                'm' => $value * 1000000,
                'g' => $value * 1000000000,
                default => $value,
            };
        }

        return (int) $bandwidth;
    }
}

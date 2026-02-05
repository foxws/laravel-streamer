<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Exceptions\InvalidStreamConfigurationException;

class StreamValidator
{
    /**
     * Validate stream configuration
     */
    public static function validate(array $stream): void
    {
        static::validateRequiredFields($stream);
        static::validateStreamType($stream);
        static::validatePaths($stream);
        static::validateOptions($stream);
    }

    protected static function validateRequiredFields(array $stream): void
    {
        $required = ['in', 'stream', 'output'];

        foreach ($required as $field) {
            if (! isset($stream[$field]) || empty($stream[$field])) {
                throw new InvalidStreamConfigurationException(
                    "Stream configuration missing required field: {$field}"
                );
            }
        }
    }

    protected static function validateStreamType(array $stream): void
    {
        $validTypes = ['video', 'audio', 'text'];

        if (! in_array($stream['stream'], $validTypes, true)) {
            throw new InvalidStreamConfigurationException(
                "Invalid stream type: {$stream['stream']}. Must be one of: ".implode(', ', $validTypes)
            );
        }
    }

    protected static function validatePaths(array $stream): void
    {
        // Validate input path is not empty and doesn't contain dangerous characters
        if (preg_match('/[;&|`$]/', $stream['in'])) {
            throw new InvalidStreamConfigurationException(
                'Input path contains potentially dangerous characters'
            );
        }

        // Validate output path
        if (preg_match('/[;&|`$]/', $stream['output'])) {
            throw new InvalidStreamConfigurationException(
                'Output path contains potentially dangerous characters'
            );
        }
    }

    protected static function validateOptions(array $stream): void
    {
        // Validate bandwidth if present
        if (isset($stream['bandwidth'])) {
            $bandwidth = (string) $stream['bandwidth'];
            if (! ctype_digit($bandwidth) || (int) $bandwidth <= 0) {
                throw new InvalidStreamConfigurationException(
                    'Bandwidth must be a positive integer'
                );
            }
        }

        // Validate segment duration if present
        if (isset($stream['segment_duration'])) {
            $duration = $stream['segment_duration'];
            if (! is_numeric($duration) || $duration <= 0) {
                throw new InvalidStreamConfigurationException(
                    'Segment duration must be a positive number'
                );
            }
        }
    }
}

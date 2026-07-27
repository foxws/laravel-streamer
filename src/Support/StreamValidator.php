<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Exceptions\InvalidStreamConfigurationException;

class StreamValidator
{
    /**
     * Validate a stream descriptor.
     *
     * Stream::fromArray()/Stream itself already guarantees 'in' and 'stream'
     * are present; this validates the constraints that only make sense in the
     * context of adding a stream to a CommandBuilder (e.g. an output path).
     */
    public static function validate(Stream $stream): void
    {
        static::validateOutput($stream);
        static::validateStreamType($stream);
        static::validatePaths($stream);
        static::validateOptions($stream);
    }

    protected static function validateOutput(Stream $stream): void
    {
        if (blank($stream->getOutput())) {
            throw new InvalidStreamConfigurationException(
                'Stream configuration missing required field: output'
            );
        }
    }

    protected static function validateStreamType(Stream $stream): void
    {
        $validTypes = ['video', 'audio', 'text'];

        if (! in_array($stream->getType(), $validTypes, true)) {
            throw new InvalidStreamConfigurationException(
                "Invalid stream type: {$stream->getType()}. Must be one of: ".implode(', ', $validTypes)
            );
        }
    }

    protected static function validatePaths(Stream $stream): void
    {
        // Validate input path doesn't contain dangerous characters
        if (preg_match('/[;&|`$]/', $stream->getInput())) {
            throw new InvalidStreamConfigurationException(
                'Input path contains potentially dangerous characters'
            );
        }

        // Validate output path
        if (preg_match('/[;&|`$]/', (string) $stream->getOutput())) {
            throw new InvalidStreamConfigurationException(
                'Output path contains potentially dangerous characters'
            );
        }
    }

    protected static function validateOptions(Stream $stream): void
    {
        $options = $stream->getOptions();

        // Validate bandwidth if present
        if (isset($options['bandwidth'])) {
            $bandwidth = (string) $options['bandwidth'];
            if (! ctype_digit($bandwidth) || (int) $bandwidth <= 0) {
                throw new InvalidStreamConfigurationException(
                    'Bandwidth must be a positive integer'
                );
            }
        }

        // Validate segment duration if present
        if (isset($options['segment_duration'])) {
            $duration = $options['segment_duration'];
            if (! is_numeric($duration) || $duration <= 0) {
                throw new InvalidStreamConfigurationException(
                    'Segment duration must be a positive number'
                );
            }
        }
    }
}

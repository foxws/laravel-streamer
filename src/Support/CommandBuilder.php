<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Builder for constructing Shaka Packager command arguments
 */
class CommandBuilder
{
    protected Collection $streams;

    protected array $options = [];

    public function __construct()
    {
        $this->streams = Collection::make();
    }

    public static function make(): self
    {
        return new self;
    }

    public function addStream(array $stream): self
    {
        $this->streams->push($stream);

        return $this;
    }

    public function addVideoStream(string $input, string $output, array $options = []): self
    {
        return $this->addStream(array_merge([
            'in' => $input,
            'stream' => 'video',
            'output' => $output,
        ], $options));
    }

    public function addAudioStream(string $input, string $output, array $options = []): self
    {
        return $this->addStream(array_merge([
            'in' => $input,
            'stream' => 'audio',
            'output' => $output,
        ], $options));
    }

    public function addTextStream(string $input, string $output, array $options = []): self
    {
        return $this->addStream(array_merge([
            'in' => $input,
            'stream' => 'text',
            'output' => $output,
        ], $options));
    }

    public function withMpdOutput(string $path): self
    {
        $this->options['mpd_output'] = $path;

        return $this;
    }

    public function withHlsMasterPlaylist(string $path): self
    {
        $this->options['hls_master_playlist_output'] = $path;

        return $this;
    }

    public function withSegmentDuration(int $seconds): self
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException('Segment duration must be at least 1 second');
        }

        $this->options['segment_duration'] = $seconds;
        $this->options['fragment_duration'] = $seconds;

        return $this;
    }

    public function withEncryption(array $encryptionConfig): self
    {
        foreach ($encryptionConfig as $key => $value) {
            $this->options[$key] = $value;
        }

        return $this;
    }

    public function withOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function build(): string
    {
        $parts = Collection::make();

        // Add stream definitions
        $this->streams->each(function (array $stream) use ($parts) {
            $streamParts = Collection::make($stream)
                ->map(function ($value, $key) {
                    $escapedKey = $this->escapeKey($key);
                    $sanitizedValue = $this->sanitizeDescriptorValue($value);

                    return sprintf('%s=%s', $escapedKey, $sanitizedValue);
                })
                ->values();

            $parts->push($streamParts->implode(','));
        });

        // Add global options - early return if empty
        if (empty($this->options)) {
            return $parts->implode(' ');
        }

        Collection::make($this->options)->each(function ($value, $key) use ($parts) {
            $escapedKey = $this->escapeKey($key);

            if (is_bool($value)) {
                if ($value) {
                    $parts->push("--{$escapedKey}");
                }
            } elseif ($value !== null && $value !== '') {
                $parts->push(sprintf(
                    '--%s=%s',
                    $escapedKey,
                    $this->escapeValue($value)
                ));
            }
        });

        return $parts->implode(' ');
    }

    public function buildArray(): array
    {
        $arguments = [];

        // Add stream definitions
        $this->streams->each(function (array $stream) use (&$arguments) {
            $streamParts = Collection::make($stream)
                ->map(function ($value, $key) {
                    $escapedKey = $this->escapeKey($key);
                    $sanitizedValue = $this->sanitizeDescriptorValue($value);

                    return sprintf('%s=%s', $escapedKey, $sanitizedValue);
                })
                ->values();

            $arguments[] = $streamParts->implode(',');
        });

        // Add global options
        Collection::make($this->options)->each(function ($value, $key) use (&$arguments) {
            if (is_bool($value)) {
                if ($value) {
                    $arguments[] = "--{$key}";
                }
            } elseif ($value !== null && $value !== '') {
                $arguments[] = "--{$key}";
                $arguments[] = (string) $value;
            }
        });

        return $arguments;
    }

    public function reset(): self
    {
        $this->streams = Collection::make();
        $this->options = [];

        return $this;
    }

    public function getStreams(): Collection
    {
        return $this->streams;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    protected function escapeKey(string $key): string
    {
        // Keys should only contain alphanumeric characters, underscores, and hyphens
        // This prevents command injection while allowing all valid Shaka Packager options
        if (! preg_match('/^[a-z0-9_-]+$/i', $key)) {
            throw new InvalidArgumentException(
                "Invalid key format: {$key}. Keys must contain only alphanumeric characters, underscores, and hyphens."
            );
        }

        return $key;
    }

    /**
     * Sanitize descriptor values to avoid Shaka stream parser issues.
     * - Normalize smart quotes to ASCII
     * - Replace commas (Shaka field separators) with hyphens
     * - Trim surrounding quotes
     * - Prefix leading dashes with ./ to avoid option-like confusion
     */
    protected function sanitizeDescriptorValue(mixed $value): string
    {
        $v = (string) $value;

        // Normalize typographic quotes
        $v = str_replace(['’', '‘', '“', '”'], ["'", "'", '"', '"'], $v);

        // Commas separate fields in Shaka descriptors; avoid them in values
        $v = str_replace(',', '-', $v);

        // Remove surrounding quotes if present
        $v = trim($v, "\"'");

        // If value starts with a dash, prefix with ./ for safety
        if (str_starts_with($v, '-')) {
            $v = './'.$v;
        }

        return $v;
    }

    protected function escapeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        // Return raw string; Shaka Packager parses stream descriptors itself and
        // quotes can break field detection (e.g., filenames with leading dashes/parentheses).
        return (string) $value;
    }
}

<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Exceptions\InvalidStreamConfigurationException;
use Foxws\Streamer\Filesystem\Media;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A single Shaka Packager-style stream descriptor (in, stream, output, + extra options).
 *
 * Immutable: mutator methods return a new instance rather than modifying the
 * current one, so a Stream can be safely shared and reused as a template.
 *
 * @phpstan-consistent-constructor Subclasses must keep this constructor's
 * signature: mutators use `new static(...)` so a subclass survives with*() calls.
 */
class Stream implements Arrayable
{
    protected function __construct(
        protected ?Media $media,
        protected ?string $input,
        protected string $type,
        protected ?string $output = null,
        protected array $options = [],
    ) {}

    public static function make(Media $media, string $type = 'video'): self
    {
        return new static($media, null, $type);
    }

    public static function video(Media $media): self
    {
        return new static($media, null, 'video');
    }

    public static function audio(Media $media): self
    {
        return new static($media, null, 'audio');
    }

    public static function text(Media $media): self
    {
        return new static($media, null, 'text');
    }

    /**
     * Build a stream descriptor directly from raw fields, e.g. as accepted by
     * CommandBuilder::addStream(). The 'in' and 'stream' keys are required;
     * any keys beyond 'in', 'stream', and 'output' are treated as additional
     * descriptor options.
     *
     * @throws InvalidStreamConfigurationException
     */
    public static function fromArray(array $stream): self
    {
        if (blank($stream['in'] ?? null)) {
            throw new InvalidStreamConfigurationException('Stream configuration missing required field: in');
        }

        if (blank($stream['stream'] ?? null)) {
            throw new InvalidStreamConfigurationException('Stream configuration missing required field: stream');
        }

        $input = $stream['in'];
        $type = $stream['stream'];
        $output = $stream['output'] ?? null;
        $options = array_diff_key($stream, array_flip(['in', 'stream', 'output']));

        return new static(null, $input, $type, $output, $options);
    }

    public function getMedia(): ?Media
    {
        return $this->media;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Resolve the input path: the Media's local path when built from Media,
     * or the raw path when built via fromArray().
     */
    public function getInput(): string
    {
        return $this->input ?? $this->media->getLocalPath();
    }

    public function setOutput(string $output): self
    {
        return new static($this->media, $this->input, $this->type, $output, $this->options);
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function setOptions(array $options): self
    {
        return new static($this->media, $this->input, $this->type, $this->output, $options);
    }

    public function addOption(string $key, mixed $value): self
    {
        return new static($this->media, $this->input, $this->type, $this->output, [...$this->options, $key => $value]);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Flat descriptor fields as consumed by Shaka Packager's stream syntax:
     * in=..,stream=..,output=..,<extra option>=..
     */
    public function toDescriptorArray(): array
    {
        $parts = [
            'in' => $this->getInput(),
            'stream' => $this->type,
        ];

        if ($this->output !== null) {
            $parts['output'] = $this->output;
        }

        return array_merge($parts, $this->options);
    }

    /**
     * Convert stream to Shaka Streamer command format
     */
    public function toCommandString(): string
    {
        $commandParts = [];

        foreach ($this->toDescriptorArray() as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $commandParts[] = $key;
                }
            } else {
                $commandParts[] = "{$key}={$value}";
            }
        }

        return implode(',', $commandParts);
    }

    public function toArray(): array
    {
        return [
            'in' => $this->getInput(),
            'stream' => $this->type,
            'output' => $this->output,
            'options' => $this->options,
        ];
    }
}

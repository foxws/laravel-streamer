<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Filesystem\Media;
use Illuminate\Contracts\Support\Arrayable;

class Stream implements Arrayable
{
    protected ?Media $media = null;

    protected string $type;

    protected ?string $output = null;

    protected array $options = [];

    public function __construct(Media $media, string $type = 'video')
    {
        $this->media = $media;
        $this->type = $type;
    }

    public static function make(Media $media, string $type = 'video'): self
    {
        return new self($media, $type);
    }

    public static function video(Media $media): self
    {
        return new self($media, 'video');
    }

    public static function audio(Media $media): self
    {
        return new self($media, 'audio');
    }

    public static function text(Media $media): self
    {
        return new self($media, 'text');
    }

    public function getMedia(): Media
    {
        return $this->media;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setOutput(string $output): self
    {
        $this->output = $output;

        return $this;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function addOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Convert stream to Shaka Packager command format
     */
    public function toCommandString(): string
    {
        $parts = [
            'in' => $this->media->getLocalPath(),
            'stream' => $this->type,
        ];

        if ($this->output) {
            $parts['output'] = $this->output;
        }

        // Merge with any additional options
        $parts = array_merge($parts, $this->options);

        $commandParts = [];

        foreach ($parts as $key => $value) {
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
            'in' => $this->media->getLocalPath(),
            'stream' => $this->type,
            'output' => $this->output,
            'options' => $this->options,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Illuminate\Support\Collection;

/**
 * Builds Shaka Streamer configuration from fluent API calls.
 *
 * This class converts fluent method calls into Shaka Streamer's config format,
 * which expects separate InputConfig and PipelineConfig structures.
 *
 * Reference: https://shaka-project.github.io/shaka-streamer/configuration_fields.html
 */
class CommandBuilder
{
    protected Collection $streams;

    protected Collection $pipelineOptions;

    protected ?string $mpdOutput = null;

    protected ?string $hlsOutput = null;

    protected string $streamingMode = 'vod';

    public function __construct()
    {
        $this->streams = new Collection;
        $this->pipelineOptions = new Collection;
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * Add video stream with codec specification
     *
     * @param  string  $input  Input file path
     * @param  string  $output  Output file name (not path)
     * @param  array  $options  Stream-specific options (bandwidth, resolution, codec, etc.)
     */
    public function addVideoStream(string $input, string $output, array $options = []): self
    {
        $this->streams->push([
            'type' => 'video',
            'input' => $input,
            'output' => $output,
            'options' => $options,
        ]);

        return $this;
    }

    /**
     * Add audio stream with codec specification
     */
    public function addAudioStream(string $input, string $output, array $options = []): self
    {
        $this->streams->push([
            'type' => 'audio',
            'input' => $input,
            'output' => $output,
            'options' => $options,
        ]);

        return $this;
    }

    /**
     * Add text/subtitle stream
     */
    public function addTextStream(string $input, string $output, array $options = []): self
    {
        $this->streams->push([
            'type' => 'text',
            'input' => $input,
            'output' => $output,
            'options' => $options,
        ]);

        return $this;
    }

    public function addStream(array $stream): self
    {
        $this->streams->push($stream);

        return $this;
    }

    /**
     * Set DASH/MPD manifest output
     */
    public function withMpdOutput(string $path): self
    {
        $this->mpdOutput = $path;

        return $this;
    }

    /**
     * Set HLS master playlist output
     */
    public function withHlsMasterPlaylist(string $path): self
    {
        $this->hlsOutput = $path;

        return $this;
    }

    /**
     * Set segment duration in seconds
     */
    public function withSegmentDuration(int $seconds): self
    {
        $this->pipelineOptions->put('segment_size', (float) $seconds);

        return $this;
    }

    /**
     * Set streaming mode (vod or live)
     */
    public function withStreamingMode(string $mode): self
    {
        $this->streamingMode = $mode;

        return $this;
    }

    /**
     * Configure encryption
     */
    public function withEncryption(array $encryptionConfig): self
    {
        $this->pipelineOptions->put('encryption', $encryptionConfig);

        return $this;
    }

    /**
     * Enable or disable segment per file output
     */
    public function withSegmentPerFile(bool $enabled = true): self
    {
        $this->pipelineOptions->put('segment_per_file', $enabled);

        return $this;
    }

    /**
     * Set the audio codecs to use for packaging
     *
     * @param  array<int, string>  $codecs  e.g. ['aac', 'opus']
     */
    public function withAudioCodecs(array $codecs): self
    {
        $this->pipelineOptions->put('audio_codecs', $codecs);

        return $this;
    }

    /**
     * Set the video codecs to use for packaging
     *
     * @param  array<int, string>  $codecs  e.g. ['h264', 'hw:vp9']
     */
    public function withVideoCodecs(array $codecs): self
    {
        $this->pipelineOptions->put('video_codecs', $codecs);

        return $this;
    }

    /**
     * Add a custom pipeline option
     */
    public function withOption(string $key, mixed $value): self
    {
        $this->pipelineOptions->put($key, $value);

        return $this;
    }

    public function getStreams(): Collection
    {
        return $this->streams;
    }

    public function getOptions(): Collection
    {
        return $this->pipelineOptions;
    }

    public function getMpdOutput(): ?string
    {
        return $this->mpdOutput;
    }

    public function getHlsOutput(): ?string
    {
        return $this->hlsOutput;
    }

    /**
     * Build complete Shaka Streamer config array
     * Returns both InputConfig and PipelineConfig as expected by Shaka Streamer
     */
    public function buildArray(): array
    {
        return $this->build();
    }

    /**
     * Build config compatible with Shaka Streamer's expected format
     *
     * @return array{input_config: array, pipeline_config: array}
     */
    public function build(): array
    {
        return [
            'input_config' => $this->buildInputConfig(),
            'pipeline_config' => $this->buildPipelineConfig(),
        ];
    }

    /**
     * Build InputConfig for Shaka Streamer
     *
     * Shaka Streamer expects:
     * {
     *   "inputs": [
     *     {"input_type": "file", "name": "path/to/file.mp4", "media_type": "video"}
     *   ]
     * }
     */
    protected function buildInputConfig(): array
    {
        $inputs = [];
        $processedInputs = [];

        foreach ($this->streams as $stream) {
            $input = $stream['input'];

            // Only add each unique input once
            if (! isset($processedInputs[$input])) {
                $inputs[] = [
                    'input_type' => 'file',
                    'name' => $input,
                    'media_type' => $stream['type'],
                ];
                $processedInputs[$input] = count($inputs) - 1;
            }
        }

        return ['inputs' => $inputs];
    }

    /**
     * Build PipelineConfig for Shaka Streamer
     *
     * Shaka Streamer expects:
     * {
     *   "streaming_mode": "vod",
     *   "resolutions": ["720p", "480p"],
     *   "manifest_format": ["dash", "hls"],
     *   "dash_output": "manifest.mpd",
     *   "hls_output": "master.m3u8",
     *   "segment_size": 10
     * }
     */
    protected function buildPipelineConfig(): array
    {
        $config = [
            'streaming_mode' => $this->streamingMode,
            'manifest_format' => $this->buildManifestFormats(),
        ];

        // Add manifest outputs
        if ($this->mpdOutput) {
            $config['dash_output'] = $this->mpdOutput;
        }

        if ($this->hlsOutput) {
            $config['hls_output'] = $this->hlsOutput;
        }

        // Merge additional pipeline options
        $config = array_merge($config, $this->pipelineOptions->toArray());

        // Set default segment size if not specified
        if (! isset($config['segment_size'])) {
            $config['segment_size'] = 10;
        }

        return $config;
    }

    /**
     * Build manifest formats array based on outputs
     */
    protected function buildManifestFormats(): array
    {
        $formats = [];

        if ($this->mpdOutput) {
            $formats[] = 'dash';
        }

        if ($this->hlsOutput) {
            $formats[] = 'hls';
        }

        return ! empty($formats) ? $formats : ['dash', 'hls'];
    }
}

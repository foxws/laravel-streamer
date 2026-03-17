<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Events\StreamingCompleted;
use Foxws\Streamer\Events\StreamingFailed;
use Foxws\Streamer\Events\StreamingStarted;
use Foxws\Streamer\Filesystem\MediaCollection;
use Foxws\Streamer\Filesystem\TemporaryDirectories;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;
use Psr\Log\LoggerInterface;
use Throwable;

class Streamer
{
    use ForwardsCalls;

    protected ShakaStreamer $streamer;

    protected ?MediaCollection $mediaCollection = null;

    protected ?LoggerInterface $logger;

    protected ?CommandBuilder $builder = null;

    protected ?string $temporaryDirectory = null;

    protected ?string $cacheDirectory = null;

    protected ?array $configuration = null;

    public function __construct(
        ShakaStreamer $streamer,
        ?LoggerInterface $logger = null,
        ?array $configuration = null
    ) {
        $this->streamer = $streamer;
        $this->logger = $logger;
        $this->configuration = $configuration;
    }

    public static function create(
        ?LoggerInterface $logger = null,
        ?array $configuration = null
    ): self {
        $streamer = ShakaStreamer::create($logger, $configuration);

        return new self($streamer, $logger, $configuration);
    }

    public function fresh(): self
    {
        return new self($this->streamer, $this->logger, $this->configuration);
    }

    public function getStreamer(): ShakaStreamer
    {
        return $this->streamer;
    }

    public function setStreamer(ShakaStreamer $streamer): self
    {
        $this->streamer = $streamer;

        return $this;
    }

    public function getMediaCollection(): MediaCollection
    {
        return $this->mediaCollection;
    }

    public function open(MediaCollection $mediaCollection): self
    {
        $this->mediaCollection = $mediaCollection;

        // Validate the media collection
        if ($mediaCollection->count() === 0) {
            throw new \InvalidArgumentException('MediaCollection cannot be empty');
        }

        // Initialize a fresh CommandBuilder for this media collection
        $this->builder = $this->createBuilder();

        if ($this->logger) {
            $this->logger->debug('Opened media collection', [
                'count' => $mediaCollection->count(),
                'paths' => $mediaCollection->getLocalPaths(),
            ]);
        }

        return $this;
    }

    public function getBuilder(): ?CommandBuilder
    {
        return $this->builder;
    }

    public function builder(): CommandBuilder
    {
        if (! $this->builder) {
            $this->builder = $this->createBuilder();
        }

        return $this->builder;
    }

    /**
     * Create a new CommandBuilder with configuration defaults
     */
    protected function createBuilder(): CommandBuilder
    {
        $builder = CommandBuilder::make();

        if (! $this->configuration) {
            return $builder;
        }

        // Apply configuration defaults
        if (filled($this->configuration['segment_duration'] ?? null)) {
            $builder->withSegmentDuration($this->configuration['segment_duration']);
        }

        if (filled($this->configuration['hwaccel_api'] ?? null)) {
            $builder->withHwaccelApi($this->configuration['hwaccel_api']);
        }

        if (filled($this->configuration['extra_input_args'] ?? null)) {
            $builder->withExtraInputArgs($this->configuration['extra_input_args']);
        }

        if (filled($this->configuration['streamer_options'] ?? null) && is_array($this->configuration['streamer_options'])) {
            foreach ($this->configuration['streamer_options'] as $key => $value) {
                $builder->withOption($key, $value);
            }
        }

        return $builder;
    }

    /**
     * Create streams from the media collection
     *
     * @return Collection<int, Stream>
     */
    public function streams(): Collection
    {
        $streams = new Collection;

        foreach ($this->mediaCollection->collection() as $media) {
            // You can create multiple streams per media (video, audio, etc.)
            $streams->push(Stream::video($media));
            $streams->push(Stream::audio($media));
            $streams->push(Stream::text($media));
        }

        return $streams;
    }

    /**
     * Add a video stream to the builder
     */
    public function addVideoStream(string $input, string $output, array $options = []): self
    {
        // Resolve input to full local path for Shaka Streamer
        $inputPath = $this->resolveInputPath($input);

        // Resolve output to full local path for Shaka Streamer
        $outputPath = $this->resolveOutputPath($output);

        $this->builder()->addVideoStream($inputPath, $outputPath, $options);

        // Apply video codec and resolution configuration defaults (only if not already set)
        if ($this->configuration) {
            if (! $this->builder()->getOptions()->has('video_codecs') && filled($this->configuration['video_codecs'] ?? null)) {
                $this->builder()->withVideoCodecs($this->parseCodecs($this->configuration['video_codecs'], ['h264']));
            }
        }

        return $this;
    }

    /**
     * Add an audio stream to the builder
     */
    public function addAudioStream(string $input, string $output, array $options = []): self
    {
        // Resolve input to full local path for Shaka Streamer
        $inputPath = $this->resolveInputPath($input);

        // Resolve output to full local path for Shaka Streamer
        $outputPath = $this->resolveOutputPath($output);

        $this->builder()->addAudioStream($inputPath, $outputPath, $options);

        // Apply audio codec configuration defaults (only if not already set)
        if ($this->configuration && ! $this->builder()->getOptions()->has('audio_codecs') && filled($this->configuration['audio_codecs'] ?? null)) {
            $this->builder()->withAudioCodecs($this->parseCodecs($this->configuration['audio_codecs'], ['aac']));
        }

        return $this;
    }

    /**
     * Add an text stream to the builder
     */
    public function addTextStream(string $input, string $output, array $options = []): self
    {
        // Resolve input to full local path for Shaka Streamer
        $inputPath = $this->resolveInputPath($input);

        // Resolve output to full local path for Shaka Streamer
        $outputPath = $this->resolveOutputPath($output);

        $this->builder()->addTextStream($inputPath, $outputPath, $options);

        return $this;
    }

    /**
     * Add a stream to the builder
     */
    public function addStream(array $stream): self
    {
        $this->builder()->addStream($stream);

        return $this;
    }

    /**
     * Resolve input path to full local path from MediaCollection
     */
    protected function resolveInputPath(string $input): string
    {
        // Try to find media in collection
        if ($this->mediaCollection) {
            $media = $this->mediaCollection->findByPath($input);

            if ($media) {
                return $media->getSafeInputPath();
            }
        }

        // If not found, assume it's already a full path
        return $input;
    }

    /**
     * Resolve output path to temporary directory for Shaka Streamer processing
     */
    protected function resolveOutputPath(string $output): string
    {
        // Get or create temporary directory
        $tempDir = $this->getTemporaryDirectory();

        // Combine with output filename (without source directory)
        return $tempDir.DIRECTORY_SEPARATOR.$output;
    }

    /**
     * Get or create temporary directory for this export
     */
    protected function getTemporaryDirectory(): string
    {
        if ($this->temporaryDirectory) {
            return $this->temporaryDirectory;
        }

        // Use the registered TemporaryDirectories service
        $this->temporaryDirectory = app(TemporaryDirectories::class)->create();

        return $this->temporaryDirectory;
    }

    /**
     * Set MPD output
     */
    public function withMpdOutput(string $path): self
    {
        $fullPath = $this->resolveOutputPath($path);

        $this->builder()->withMpdOutput($fullPath);

        return $this;
    }

    /**
     * Set HLS master playlist output
     */
    public function withHlsMasterPlaylist(string $path): self
    {
        $fullPath = $this->resolveOutputPath($path);

        $this->builder()->withHlsMasterPlaylist($fullPath);

        return $this;
    }

    /**
     * Enable AES-128 encryption with auto-generated keys.
     *
     * Generates encryption key, writes to cache storage, and configures Shaka Streamer.
     * When used with withKeyRotationDuration(), the filename becomes a base name
     * (e.g., 'key' becomes 'key_0', 'key_1', 'key_2', etc. in cache storage).
     *
     * Protection schemes:
     * - 'cenc' (AES-CTR): Recommended for Widevine/PlayReady, supports key rotation
     * - 'cbcs' (AES-CBC): For FairPlay/Safari
     * - 'cbc1': Legacy HLS, limited browser support
     * - null: SAMPLE-AES, widest compatibility but NO key rotation support
     *
     * @param  string  $keyFilename  Base name for key file (default: 'key')
     * @param  string|null  $protectionScheme  Protection scheme ('cenc', 'cbcs', 'cbc1', or null)
     * @param  string|null  $label  Optional label for multi-key scenarios
     * @return array{key: string, key_id: string, file_path: string} Encryption key data
     */
    public function withAESEncryption(string $keyFilename = 'key', ?string $protectionScheme = null, ?string $label = null): array
    {
        // Generate key and write to cache storage (fast)
        $keyData = EncryptionKeyGenerator::generateAndWrite($keyFilename);

        // Store cache directory for later use in StreamerResult
        $this->cacheDirectory = dirname($keyData['file_path']);

        // Build Shaka Streamer EncryptionConfig object
        // Ref: https://shaka-project.github.io/shaka-streamer/configuration_fields.html#pipeline-configs
        $encryptionConfig = [
            'enable' => true,
            'encryption_mode' => 'raw',
            'clear_lead' => 0,
            'keys' => [
                [
                    'label' => $label ?? '',
                    'key_id' => $keyData['key_id'],
                    'key' => $keyData['key'],
                ],
            ],
        ];

        if (filled($protectionScheme)) {
            $encryptionConfig['protection_scheme'] = $protectionScheme;
        }

        $this->builder()->withEncryption($encryptionConfig);

        return $keyData;
    }

    /**
     * Enable key rotation for encryption.
     *
     * Sets the crypto_period_duration inside the encryption config.
     * Call after withAESEncryption().
     *
     * IMPORTANT: Key rotation requires protection scheme 'cenc' or 'cbcs'.
     * SAMPLE-AES (null) does not support key rotation.
     *
     * @param  int  $seconds  Duration in seconds before rotating to a new key
     */
    public function withKeyRotationDuration(int $seconds): self
    {
        // Merge crypto_period_duration into the existing encryption config
        $existingEncryption = $this->builder()->getOptions()->get('encryption', []);
        $existingEncryption['crypto_period_duration'] = $seconds;

        $this->builder()->withEncryption($existingEncryption);

        return $this;
    }

    /**
     * Enable or disable using system binaries for streaming
     *
     * @param  bool  $use  Whether to use system binaries
     */
    public function useSystemBinaries(bool $use = true): self
    {
        if ($use) {
            $this->streamer->addArgument('--use-system-binaries');
        }

        return $this;
    }

    /**
     * Returns the final config that would be executed, useful for debugging purposes.
     */
    public function getCommand(): array
    {
        if (! $this->builder) {
            throw new \RuntimeException('No streams configured. Use addVideoStream() or addAudioStream() first.');
        }

        return $this->builder->build();
    }

    /**
     * Filter sensitive data from options before logging
     */
    protected function filterSensitiveOptions(array $options): array
    {
        // List of sensitive keys that should be redacted
        static $sensitiveKeys = [
            'keys' => true,
            'key' => true,
            'key_id' => true,
            'pssh' => true,
            'protection_systems' => true,
            'raw_key' => true,
            'iv' => true,
        ];

        $filtered = $options;

        foreach ($sensitiveKeys as $key => $_) {
            if (isset($filtered[$key])) {
                $filtered[$key] = '[REDACTED]';
            }
        }

        return $filtered;
    }

    /**
     * Export streaming with the configured builder
     */
    public function export(): StreamerResult
    {
        if (! $this->builder) {
            throw new \RuntimeException('No streams configured. Use addVideoStream() or addAudioStream() first.');
        }

        $config = $this->builder->buildArray();

        if ($this->logger) {
            $this->logger->info('Starting streaming operation', [
                'streams' => $this->builder->getStreams()->count(),
                'options' => $this->filterSensitiveOptions($this->builder->getOptions()->toArray()),
            ]);
        }

        // Dispatch event before starting the streaming operation
        StreamingStarted::dispatch($this->mediaCollection, $config);

        // Start timer for execution time measurement
        $startTime = microtime(true);

        try {
            // Get temporary directory for output
            $outputDirectory = $this->getTemporaryDirectory();

            // Pass temp directory to shaka-streamer via -o flag
            $rawResult = $this->streamer->packageWithConfig($config, $outputDirectory);

            // Get the first media's disk as the source disk
            $sourceDisk = $this->mediaCollection->collection()->first()?->getDisk();

            $result = new StreamerResult($rawResult, $sourceDisk, $this->temporaryDirectory, $this->cacheDirectory, $this->configuration);

            if ($this->logger) {
                $this->logger->info('Streaming operation completed');
            }

            StreamingCompleted::dispatch($result, microtime(true) - $startTime);

            return $result;
        } catch (Throwable $e) {
            $executionTime = microtime(true) - $startTime;

            if ($this->logger) {
                $this->logger->error('Streaming operation failed', [
                    'exception' => $e->getMessage(),
                    'execution_time' => $executionTime,
                ]);
            }

            // Dispatch event on failure with exception and execution time
            StreamingFailed::dispatch($e, $executionTime);

            throw $e;
        }
    }

    public function streamWithBuilder(CommandBuilder $builder): StreamerResult
    {
        $config = $builder->buildArray();

        if ($this->logger) {
            $this->logger->info('Starting streaming operation with builder', [
                'streams' => $builder->getStreams()->count(),
                'options' => $this->filterSensitiveOptions($builder->getOptions()->toArray()),
            ]);
        }

        if ($this->mediaCollection) {
            StreamingStarted::dispatch($this->mediaCollection, $config);
        }

        $startTime = microtime(true);

        try {
            $outputDirectory = $this->getTemporaryDirectory();

            $rawResult = $this->streamer->packageWithConfig($config, $outputDirectory);

            $sourceDisk = $this->mediaCollection?->collection()->first()?->getDisk();

            $result = new StreamerResult($rawResult, $sourceDisk, $this->temporaryDirectory, $this->cacheDirectory, $this->configuration);

            if ($this->logger) {
                $this->logger->info('Streaming operation completed');
            }

            // Dispatch completed event with result and execution time
            StreamingCompleted::dispatch($result, microtime(true) - $startTime);

            return $result;
        } catch (Throwable $e) {
            $executionTime = microtime(true) - $startTime;

            if ($this->logger) {
                $this->logger->error('Streaming operation failed', [
                    'exception' => $e->getMessage(),
                    'execution_time' => $executionTime,
                ]);
            }

            // Dispatch failed event with exception and execution time
            StreamingFailed::dispatch($e, $executionTime);

            throw $e;
        }
    }

    /**
     * Parse comma-separated string or return default
     */
    protected function parseCodecs(string|array|null $value, array $default = []): array
    {
        if (empty($value)) {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        return array_map('trim', explode(',', $value));
    }

    /**
     * Forward all other method calls to the underlying CommandBuilder,
     * returning $this for fluent chaining when the builder returns itself.
     */
    public function __call(string $name, array $arguments): mixed
    {
        $result = $this->forwardCallTo($this->builder(), $name, $arguments);

        return $result instanceof CommandBuilder ? $this : $result;
    }
}

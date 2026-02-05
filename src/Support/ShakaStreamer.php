<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Illuminate\Support\Facades\Process;
use Psr\Log\LoggerInterface;

/**
 * Shaka Streamer integration layer
 *
 * Invokes Shaka Streamer via Python module installed globally via pip.
 * Installation: pip install shaka-streamer
 * Verification: python3 -m streamer.main --version
 */
class ShakaStreamer
{
    protected ?LoggerInterface $logger;

    protected int $timeout = 3600;

    protected string $streamerBinary = 'shaka-streamer';

    protected array $additionalArguments = [];

    public function __construct(
        ?LoggerInterface $logger = null,
        int $timeout = 3600,
        string $streamerBinary = 'shaka-streamer'
    ) {
        $this->logger = $logger;
        $this->timeout = $timeout;
        $this->streamerBinary = $streamerBinary;
    }

    public static function create(
        ?LoggerInterface $logger = null,
        ?array $configuration = null
    ): self {
        $timeout = $configuration['timeout'] ?? 3600;
        $streamerBinary = $configuration['streamer.streamer_binary'] ?? $configuration['streamer_binary'] ?? 'shaka-streamer';

        return new self($logger, $timeout, $streamerBinary);
    }

    public function getStreamerBinary(): string
    {
        return $this->streamerBinary;
    }

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function setLogger(?LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setAdditionalArguments(array $arguments): self
    {
        $this->additionalArguments = $arguments;

        return $this;
    }

    public function addArgument(string $argument): self
    {
        $this->additionalArguments[] = $argument;

        return $this;
    }

    public function getAdditionalArguments(): array
    {
        return $this->additionalArguments;
    }

    /**
     * Package with Shaka Streamer config
     *
     * @param  array  $config  Configuration array with 'input_config' and 'pipeline_config'
     * @return string Output from Shaka Streamer
     *
     * @throws \RuntimeException
     */
    public function packageWithConfig(array $config): string
    {
        // Validate configuration
        $this->validateConfig($config);

        if ($this->logger) {
            $this->logger->info('Starting Shaka Streamer streaming', [
                'inputs' => count($config['input_config']['inputs'] ?? []),
                'outputs' => count($config['pipeline_config']['manifest_format'] ?? []),
            ]);
        }

        // Verify Shaka Streamer is installed
        $this->verifyInstallation();

        // Create temporary config files
        [$inputConfigFile, $pipelineConfigFile] = $this->createConfigFiles($config);

        try {
            $output = $this->invokeStreamer($inputConfigFile, $pipelineConfigFile);

            if ($this->logger) {
                $this->logger->info('Shaka Streamer completed successfully');
            }

            return $output;
        } finally {
            // Always clean up temp files
            if (file_exists($inputConfigFile)) {
                unlink($inputConfigFile);
            }
            if (file_exists($pipelineConfigFile)) {
                unlink($pipelineConfigFile);
            }
        }
    }

    /**
     * Verify Shaka Streamer is installed and accessible
     *
     * @throws \RuntimeException
     */
    protected function verifyInstallation(): void
    {
        $result = Process::timeout(10)
            ->run(['which', $this->streamerBinary]);

        if ($result->failed()) {
            throw new \RuntimeException(
                'Shaka Streamer is not installed or not accessible. '.
                'Install with: pip install shaka-streamer'
            );
        }

        if ($this->logger) {
            $this->logger->debug('Shaka Streamer verified', [
                'path' => trim($result->output()),
            ]);
        }
    }

    /**
     * Create temporary configuration files for input and pipeline configs
     *
     * @param  array  $config  Configuration array
     * @return array [inputConfigPath, pipelineConfigPath]
     *
     * @throws \RuntimeException
     */
    protected function createConfigFiles(array $config): array
    {
        $inputConfigFile = tempnam(sys_get_temp_dir(), 'shaka_input_');
        $pipelineConfigFile = tempnam(sys_get_temp_dir(), 'shaka_pipeline_');

        if ($inputConfigFile === false || $pipelineConfigFile === false) {
            throw new \RuntimeException('Failed to create temporary config files');
        }

        // Create input config JSON
        $inputJson = json_encode($config['input_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (json_last_error() !== JSON_ERROR_NONE) {
            unlink($inputConfigFile);
            unlink($pipelineConfigFile);
            throw new \RuntimeException(
                'Failed to encode input configuration: '.json_last_error_msg()
            );
        }

        // Create pipeline config JSON
        $pipelineJson = json_encode($config['pipeline_config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (json_last_error() !== JSON_ERROR_NONE) {
            unlink($inputConfigFile);
            unlink($pipelineConfigFile);
            throw new \RuntimeException(
                'Failed to encode pipeline configuration: '.json_last_error_msg()
            );
        }

        // Write input config
        if (file_put_contents($inputConfigFile, $inputJson) === false) {
            unlink($inputConfigFile);
            unlink($pipelineConfigFile);
            throw new \RuntimeException('Failed to write input configuration file');
        }

        // Write pipeline config
        if (file_put_contents($pipelineConfigFile, $pipelineJson) === false) {
            unlink($inputConfigFile);
            unlink($pipelineConfigFile);
            throw new \RuntimeException('Failed to write pipeline configuration file');
        }

        if ($this->logger) {
            $this->logger->debug('Created temporary config files', [
                'input_config' => $inputConfigFile,
                'pipeline_config' => $pipelineConfigFile,
            ]);
        }

        return [$inputConfigFile, $pipelineConfigFile];
    }

    /**
     * Invoke Shaka Streamer with configuration
     *
     * @param  string  $inputConfigFile  Path to input config file
     * @param  string  $pipelineConfigFile  Path to pipeline config file
     * @return string Process output
     *
     * @throws \RuntimeException
     */
    protected function invokeStreamer(string $inputConfigFile, string $pipelineConfigFile): string
    {
        $command = [
            $this->streamerBinary,
            '-i',
            $inputConfigFile,
            '-p',
            $pipelineConfigFile,
            ...$this->additionalArguments,
        ];

        if ($this->logger) {
            $this->logger->debug('Invoking Shaka Streamer', [
                'command' => implode(' ', $command),
                'timeout' => $this->timeout,
            ]);
        }

        $result = Process::timeout($this->timeout)
            ->run($command);

        if ($result->failed()) {
            $this->handleProcessFailure($result);
        }

        return $result->output();
    }

    /**
     * Handle process failure with detailed logging
     *
     * @param  \Illuminate\Contracts\Process\ProcessResult  $result
     *
     * @throws \RuntimeException
     */
    protected function handleProcessFailure($result): void
    {
        $errorOutput = $result->errorOutput();
        $output = $result->output();

        if ($this->logger) {
            $this->logger->error('Shaka Streamer failed', [
                'exit_code' => $result->exitCode(),
                'stderr' => $errorOutput,
                'stdout' => $output,
            ]);
        }

        // Map common errors
        if (str_contains($errorOutput, 'No such file or directory')) {
            throw new \RuntimeException('Input file not found');
        }

        if (str_contains($errorOutput, 'Permission denied')) {
            throw new \RuntimeException('Permission denied accessing files');
        }

        if (str_contains($errorOutput, 'Invalid configuration')) {
            throw new \RuntimeException('Invalid Shaka Streamer configuration');
        }

        throw new \RuntimeException(
            "Shaka Streamer failed (exit code {$result->exitCode()}): {$errorOutput}"
        );
    }

    /**
     * Validate configuration structure
     *
     * @param  array  $config  Configuration array
     *
     * @throws \RuntimeException
     */
    protected function validateConfig(array $config): void
    {
        // Check for required top-level keys
        if (empty($config['input_config'])) {
            throw new \RuntimeException('Configuration missing "input_config" key');
        }

        if (empty($config['pipeline_config'])) {
            throw new \RuntimeException('Configuration missing "pipeline_config" key');
        }

        // Validate input_config
        if (empty($config['input_config']['inputs'])) {
            throw new \RuntimeException('input_config must have "inputs" array');
        }

        if (! is_array($config['input_config']['inputs'])) {
            throw new \RuntimeException('input_config "inputs" must be an array');
        }

        // Validate each input
        foreach ($config['input_config']['inputs'] as $index => $input) {
            if (empty($input['name'])) {
                throw new \RuntimeException("Input {$index} missing 'name' field");
            }

            if (empty($input['media_type'])) {
                throw new \RuntimeException("Input {$index} missing 'media_type' field");
            }

            if (! file_exists($input['name'])) {
                throw new \RuntimeException("Input file not found: {$input['name']}");
            }
        }

        // Validate pipeline_config
        if (empty($config['pipeline_config']['manifest_format'])) {
            throw new \RuntimeException('pipeline_config missing "manifest_format"');
        }

        if (! is_array($config['pipeline_config']['manifest_format'])) {
            throw new \RuntimeException('manifest_format must be an array');
        }

        // Validate manifest formats
        $validFormats = ['dash', 'hls'];
        foreach ($config['pipeline_config']['manifest_format'] as $format) {
            if (! in_array($format, $validFormats)) {
                throw new \RuntimeException(
                    "Invalid manifest format: {$format}. Valid formats: ".implode(', ', $validFormats)
                );
            }
        }

        if ($this->logger) {
            $this->logger->debug('Configuration validated successfully');
        }
    }
}

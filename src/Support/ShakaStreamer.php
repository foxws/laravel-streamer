<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Foxws\Streamer\Exceptions\ExecutableNotFoundException;
use Foxws\Streamer\Exceptions\RuntimeException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Psr\Log\LoggerInterface;

class ShakaStreamer
{
    protected string $binaryPath;

    protected ?LoggerInterface $logger;

    protected int $timeout;

    public function __construct(
        string $binaryPath,
        ?LoggerInterface $logger = null,
        int $timeout = 3600
    ) {
        $this->binaryPath = $binaryPath;
        $this->logger = $logger;
        $this->timeout = $timeout;
    }

    public static function create(
        ?LoggerInterface $logger = null,
        ?array $configuration = null
    ): self {
        $config = $configuration ?? Config::get('laravel-streamer');

        $binaryPath = $config['packager']['binaries'] ?? '/usr/local/bin/packager';

        $timeout = $config['timeout'] ?? 3600;

        // Verify binary exists
        if (! static::isExecutable($binaryPath)) {
            throw new ExecutableNotFoundException(
                "Shaka Packager binary not found at: {$binaryPath}"
            );
        }

        return new self($binaryPath, $logger, $timeout);
    }

    protected static function isExecutable(string $path): bool
    {
        return is_file($path) && is_executable($path);
    }

    public function getName(): string
    {
        return 'packager';
    }

    public function getVersion(): string
    {
        $result = $this->command('--version');

        if (preg_match('/packager version (.+)/i', $result, $matches)) {
            return trim($matches[1]);
        }

        throw new RuntimeException('Cannot parse packager version');
    }

    public function command(string|array $command, array $options = []): string
    {
        $arguments = is_array($command) ? $command : [$command];

        if ($this->logger) {
            $this->logger->debug('Executing packager command', [
                'command' => $this->redactSensitiveData(implode(' ', $arguments)),
                'options' => $options,
            ]);
        }

        $result = Process::timeout($this->timeout)
            ->run([$this->binaryPath, ...$arguments]);

        if ($result->failed()) {
            $errorMessage = "Packager command failed: {$result->errorOutput()}";

            if ($this->logger) {
                $this->logger->error($errorMessage, [
                    'command' => $this->redactSensitiveData(implode(' ', $arguments)),
                ]);
            }

            throw new RuntimeException($errorMessage);
        }

        if ($this->logger) {
            $this->logger->debug('Packager command completed', [
                'output' => $result->output(),
            ]);
        }

        return $result->output();
    }

    protected function redactSensitiveData(string $commandLine): string
    {
        static $sensitiveOptions = ['keys', 'key', 'key_id', 'pssh', 'protection_systems', 'raw_key', 'iv'];

        $redacted = $commandLine;

        foreach ($sensitiveOptions as $option) {
            // Redact --option=value format
            $redacted = preg_replace(
                '/--'.preg_quote($option, '/').'=([^\s]+)/',
                '--'.$option.'=[REDACTED]',
                $redacted
            );

            // Redact --option value format
            $redacted = preg_replace(
                '/--'.preg_quote($option, '/').'\s+(?!--)(\S+)/',
                '--'.$option.' [REDACTED]',
                $redacted
            );
        }

        return $redacted;
    }

    public function getBinaryPath(): string
    {
        return $this->binaryPath;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function setLogger(?LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}

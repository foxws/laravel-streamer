<?php

declare(strict_types=1);

namespace Foxws\Streamer;

use Foxws\Media\Concerns\HasContext;
use Foxws\Media\Concerns\HasSaveCallbacks;
use Foxws\Media\Encoding\Ladder;
use Foxws\Media\Encoding\Rendition;
use Foxws\Media\Encoding\VideoCodec;
use Foxws\Media\Encryption\EncryptionKey;
use Foxws\Media\Encryption\ProtectionScheme;
use Foxws\Media\Events\ExportCompleted;
use Foxws\Media\Events\ExportFailed;
use Foxws\Media\Exceptions\InvalidMediaException;
use Foxws\Media\Exceptions\ProcessFailedException;
use Foxws\Media\Executables\Executable;
use Foxws\Media\Executables\Executables;
use Foxws\Media\Filesystem\Disk;
use Foxws\Media\Filesystem\Exporter;
use Foxws\Media\Filesystem\ExportResult;
use Foxws\Media\Filesystem\TemporaryDirectories;
use Foxws\Media\Opener;
use Foxws\Media\Packaging\Encryption;
use Foxws\Media\Packaging\StreamType;
use Foxws\Media\Process\Runner;
use Foxws\Streamer\Exceptions\StreamerException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;
use Throwable;

/**
 * Runs Shaka Streamer on the opened media: it encodes every stream into the chosen resolutions
 * and codecs with FFmpeg, then packages them into HLS and DASH with Shaka Packager.
 *
 * @see https://shaka-project.github.io/shaka-streamer/configuration_fields.html
 */
class StreamerBuilder
{
    use Conditionable;
    use HasContext;
    use HasSaveCallbacks;

    /**
     * Shaka Streamer's resolution names, by the height of the short side.
     *
     * @var array<int, string>
     */
    public const array RESOLUTIONS = [
        144 => '144p',
        240 => '240p',
        360 => '360p',
        480 => '480p',
        576 => '576p',
        720 => '720p',
        1080 => '1080p',
        1440 => '1440p',
        2160 => '4k',
        4320 => '8k',
    ];

    /** @var list<array<string, mixed>> */
    protected array $inputs = [];

    /** @var array<string, mixed> */
    protected array $options = [];

    protected ?string $hlsPlaylist = null;

    protected ?string $dashManifest = null;

    protected ?Encryption $encryption = null;

    protected ?string $ffmpegInputArguments = null;

    protected bool $systemBinaries = false;

    protected ?Disk $targetDisk = null;

    protected ?string $visibility = null;

    protected ?int $timeout = null;

    public function __construct(
        protected Opener $opener,
        protected Runner $runner,
        protected Executables $executables,
        protected TemporaryDirectories $directories,
        protected Exporter $exporter,
    ) {
        $this->applyDefaults();
    }

    /**
     * Encode the video stream of a file (the first opened one by default).
     *
     * @param  array<string, mixed>  $options  Other input fields, e.g. ['track_num' => 1, 'start_time' => '00:01:00'].
     */
    public function addVideoStream(?string $path = null, array $options = []): static
    {
        return $this->addInput(StreamType::Video, $path, null, $options);
    }

    /**
     * Encode the audio stream of a file (the first opened one by default).
     *
     * @param  array<string, mixed>  $options  Other input fields, e.g. ['track_num' => 1].
     */
    public function addAudioStream(?string $path = null, ?string $language = null, array $options = []): static
    {
        return $this->addInput(StreamType::Audio, $path, $language, $options);
    }

    /**
     * Add a subtitle file (e.g. WebVTT) from the disk the media was opened from.
     *
     * @param  array<string, mixed>  $options
     */
    public function addTextStream(string $path, ?string $language = null, array $options = []): static
    {
        return $this->addInput(StreamType::Text, $path, $language, $options);
    }

    /**
     * Add the video and audio streams every opened file (or the given ones) contains, found by probing them.
     *
     * @param  list<string>|null  $paths
     */
    public function addStreamsFrom(?array $paths = null): static
    {
        foreach ($paths ?? $this->opener->paths() as $path) {
            $probe = $this->opener->probe($path);
            $audio = $probe->audioStream();

            if ($probe->hasVideo()) {
                $this->addVideoStream($path);
            }

            if ($audio !== null) {
                $this->addAudioStream($path, $audio->language !== 'und' ? $audio->language : null);
            }
        }

        return $this;
    }

    /**
     * The resolutions to encode, e.g. withResolutions('1080p', '720p', '480p'). Shaka Streamer
     * skips resolutions above the input's.
     */
    public function withResolutions(string ...$resolutions): static
    {
        if ($resolutions === [] || in_array('', $resolutions, true)) {
            throw new InvalidArgumentException('Pass at least one resolution, e.g. "720p".');
        }

        return $this->withOption('resolutions', array_values(array_unique($resolutions)));
    }

    /**
     * The resolutions and video codec of a laravel-media ladder. Shaka Streamer chooses the bitrates.
     *
     * @throws InvalidArgumentException
     */
    public function withLadder(Ladder $ladder): static
    {
        $this->withResolutions(...array_map(fn (Rendition $rendition): string => self::RESOLUTIONS[$rendition->height]
            ?? throw new InvalidArgumentException("Shaka Streamer has no resolution for a height of [{$rendition->height}]; use one of ".implode(', ', array_keys(self::RESOLUTIONS)).'.'), $ladder->renditions));

        return $this->withVideoCodecs(match ($ladder->codec) {
            VideoCodec::Hevc => 'hevc',
            VideoCodec::Av1 => 'av1',
            default => 'h264',
        });
    }

    /**
     * The video codecs to encode, each into every resolution, e.g. "h264", "vp9", "av1" or "hevc".
     * Prefix a codec with "hw:" to encode it with withHardwareAcceleration().
     */
    public function withVideoCodecs(string ...$codecs): static
    {
        return $this->withOption('video_codecs', $this->list('video codec', $codecs));
    }

    /**
     * The audio codecs to encode, e.g. "aac" or "opus".
     */
    public function withAudioCodecs(string ...$codecs): static
    {
        return $this->withOption('audio_codecs', $this->list('audio codec', $codecs));
    }

    /**
     * The audio channel layouts to encode, e.g. "stereo" or "surround".
     */
    public function withChannelLayouts(string ...$layouts): static
    {
        return $this->withOption('channel_layouts', $this->list('channel layout', $layouts));
    }

    /**
     * The hardware encoder API for "hw:" codecs, e.g. "vaapi", "nvenc" or "videotoolbox".
     */
    public function withHardwareAcceleration(string $api): static
    {
        return $this->withOption('hwaccel_api', $this->list('hardware acceleration API', [$api])[0]);
    }

    public function withHlsPlaylist(string $path = 'master.m3u8'): static
    {
        $this->hlsPlaylist = ltrim($path, '/');

        return $this;
    }

    public function withDashManifest(string $path = 'manifest.mpd'): static
    {
        $this->dashManifest = ltrim($path, '/');

        return $this;
    }

    public function segmentDuration(float $seconds): static
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('The segment duration must be a positive number of seconds.');
        }

        return $this->withOption('segment_size', $seconds);
    }

    /**
     * Write each segment to its own file, instead of one file per stream with byte ranges.
     */
    public function segmentPerFile(bool $enabled = true): static
    {
        return $this->withOption('segment_per_file', $enabled);
    }

    /**
     * Add HLS I-frame playlists for fast seeking.
     */
    public function withTrickPlay(bool $enabled = true): static
    {
        return $this->withOption('generate_iframe_playlist', $enabled);
    }

    public function lowLatencyDashMode(bool $enabled = true): static
    {
        return $this->withOption('low_latency_dash_mode', $enabled);
    }

    /**
     * Encrypt the segments with AES (Common Encryption), using a new random key unless one is given.
     * The raw key is written next to the segments as the key file, and HLS playlists point to it.
     * Keep that disk private and serve the key through an authorized route, or pass keyFile: null
     * and keyUri to serve a stored key yourself. DASH players need the key themselves.
     *
     * @param  float  $clearLead  Seconds at the start left unencrypted, so playback can start before the key is fetched.
     *
     * @throws InvalidArgumentException
     */
    public function withEncryption(
        ?EncryptionKey $key = null,
        ?ProtectionScheme $scheme = null,
        ?string $keyFile = 'key',
        ?string $keyUri = null,
        ?string $label = null,
        float $clearLead = 0.0,
    ): static {
        if (! in_array($scheme, [null, ProtectionScheme::Cenc, ProtectionScheme::Cbcs], true)) {
            throw new InvalidArgumentException("Shaka Streamer only encrypts with cenc or cbcs, not [{$scheme->value}].");
        }

        $this->encryption = new Encryption(
            key: $key ?? EncryptionKey::generate(),
            scheme: $scheme,
            keyFile: $keyFile,
            keyUri: $keyUri,
            clearLead: $clearLead,
            label: $label,
        );

        return $this;
    }

    /**
     * The key the segments will be encrypted with, if encryption is enabled.
     */
    public function encryptionKey(): ?EncryptionKey
    {
        return $this->encryption?->key;
    }

    /**
     * FFmpeg input arguments for every input, e.g. "-hwaccel vaapi". An input's own
     * extra_input_args option takes precedence.
     */
    public function withFFmpegInputArgs(string $arguments): static
    {
        $this->ffmpegInputArguments = trim($arguments) !== '' ? trim($arguments) : null;

        return $this;
    }

    /**
     * Run the FFmpeg and Shaka Packager on the PATH instead of the ones from shaka-streamer-binaries.
     * laravel-media's ffmpeg and ffprobe, and the configured packager, go first in that PATH.
     */
    public function useSystemBinaries(bool $use = true): static
    {
        $this->systemBinaries = $use;

        return $this;
    }

    /**
     * Set any pipeline config field, e.g. withOption('streaming_mode', 'live'). Null removes it.
     */
    public function withOption(string $name, mixed $value): static
    {
        if ($value === null) {
            unset($this->options[$name]);

            return $this;
        }

        $this->options[$name] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): static
    {
        foreach ($options as $name => $value) {
            $this->withOption($name, $value);
        }

        return $this;
    }

    /**
     * The disk to save to. Defaults to the disk the media was opened from.
     */
    public function toDisk(Disk|Filesystem|string $disk): static
    {
        $this->targetDisk = Disk::make($disk);

        return $this;
    }

    public function withVisibility(string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * The maximum seconds Shaka Streamer may run, instead of the configured streamer.timeout.
     * Keep it at or below the queue job's $timeout, so the job can handle the failure.
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function disk(): Disk
    {
        return $this->targetDisk ?? $this->opener->disk();
    }

    public function media(): Opener
    {
        return $this->opener;
    }

    /**
     * The input and pipeline configs Shaka Streamer runs with.
     *
     * @return array{input: array{inputs: list<array<string, mixed>>}, pipeline: array<string, mixed>}
     *
     * @throws InvalidMediaException
     * @throws StreamerException
     */
    public function config(): array
    {
        if ($this->inputs === []) {
            throw InvalidMediaException::noStreams();
        }

        if ($this->manifests() === []) {
            throw StreamerException::noManifests();
        }

        $inputs = array_map(fn (array $input): array => [
            'name' => $input['media']->localPath(),
            ...array_diff_key($input, ['media' => true]),
            ...(! isset($input['extra_input_args']) && $this->ffmpegInputArguments !== null ? ['extra_input_args' => $this->ffmpegInputArguments] : []),
        ], $this->inputs);

        return [
            'input' => ['inputs' => array_values(array_unique($inputs, SORT_REGULAR))],
            'pipeline' => [
                'streaming_mode' => 'vod',
                'manifest_format' => array_keys(array_filter(['dash' => $this->dashManifest, 'hls' => $this->hlsPlaylist], fn (?string $manifest): bool => $manifest !== null)),
                ...($this->dashManifest !== null ? ['dash_output' => $this->dashManifest] : []),
                ...($this->hlsPlaylist !== null ? ['hls_output' => $this->hlsPlaylist] : []),
                ...($this->encryption !== null ? ['encryption' => $this->encryptionConfig($this->encryption)] : []),
                ...$this->options,
            ],
        ];
    }

    /**
     * The full command line, with the configs in the given directory, without running it.
     */
    public function command(string $configDirectory = 'config', string $outputDirectory = 'output'): string
    {
        $this->config();

        return $this->runner->commandLine(StreamerExecutable::ShakaStreamer, $this->arguments("{$configDirectory}/input.yaml", "{$configDirectory}/pipeline.yaml", $outputDirectory));
    }

    /**
     * Encode and package the streams, and save the segments and manifests to a directory on the
     * target disk. The result lists the manifests first.
     *
     * @throws InvalidMediaException
     * @throws ProcessFailedException
     * @throws StreamerException
     */
    public function save(string $directory = ''): ExportResult
    {
        $config = $this->config();

        $this->runBeforeSavingCallbacks();

        $startedAt = hrtime(true);

        try {
            $result = $this->export($config, trim($directory, '/'));
        } catch (Throwable $exception) {
            Event::dispatch(new ExportFailed($exception, $this->context));

            throw $exception;
        }

        $this->runAfterSavingCallbacks($result);

        Event::dispatch(new ExportCompleted($result, $this->context, (hrtime(true) - $startedAt) / 1e9));

        return $result;
    }

    /**
     * Write the configs to a cache directory (they may hold the key), run Shaka Streamer into a
     * temporary directory, and move or upload its output to the target disk.
     *
     * @param  array{input: array{inputs: list<array<string, mixed>>}, pipeline: array<string, mixed>}  $config
     */
    protected function export(array $config, string $directory): ExportResult
    {
        $configs = $this->directories->createCache();
        $output = $this->directories->create($this->opener->mediaFor()->size());

        try {
            $input = $configs->put('input.yaml', json_encode($config['input'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $pipeline = $configs->put('pipeline.yaml', json_encode($config['pipeline'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $this->runner->run(
                StreamerExecutable::ShakaStreamer,
                $this->arguments($input, $pipeline, $output->path()),
                timeout: $this->timeout ?? Config::integer('streamer.timeout', 14400),
                environment: $this->environment(),
            );

            foreach ($this->manifests() as $manifest) {
                if (! is_file($output->path($manifest))) {
                    throw StreamerException::missingManifest($manifest);
                }
            }

            if ($this->encryption?->keyFile !== null) {
                $output->put($this->encryption->keyFile, $this->encryption->key->binary());
            }

            $written = $this->exporter->export($output->path(), $this->disk(), $directory, $this->visibility, move: true);
        } finally {
            $configs->delete();
            $output->delete();
        }

        $manifests = array_map(fn (string $manifest): string => ltrim("{$directory}/{$manifest}", '/'), $this->manifests());

        return new ExportResult($this->disk(), array_values(array_unique([
            ...array_intersect($manifests, $written),
            ...$written,
        ])), $this->encryption?->key);
    }

    /**
     * @return list<string>
     */
    protected function arguments(string $input, string $pipeline, string $output): array
    {
        return ['-i', $input, '-p', $pipeline, '-o', $output, ...($this->systemBinaries ? ['--use-system-binaries'] : [])];
    }

    /**
     * Shaka Streamer runs ffmpeg, ffprobe and packager from the PATH with system binaries, so put
     * the ones laravel-media and this package are configured with in front of it.
     *
     * @return array<string, string>
     */
    protected function environment(): array
    {
        $packager = Config::get('streamer.packager');

        $directories = array_values(array_unique(array_filter(
            [
                ...array_map(fn (Executable $executable): string => dirname($this->executables->path($executable)), [Executable::FFMpeg, Executable::FFProbe]),
                ...(is_string($packager) && $packager !== '' ? [dirname($packager)] : []),
            ],
            fn (string $directory): bool => $directory !== '.',
        )));

        if ($directories === []) {
            return [];
        }

        $path = getenv('PATH');

        return ['PATH' => implode(PATH_SEPARATOR, [...$directories, ...(is_string($path) && $path !== '' ? [$path] : [])])];
    }

    /**
     * @return array<string, mixed>
     */
    protected function encryptionConfig(Encryption $encryption): array
    {
        return array_filter([
            'enable' => true,
            'encryption_mode' => 'raw',
            'keys' => [['label' => $encryption->label ?? '', 'key_id' => $encryption->key->keyId, 'key' => $encryption->key->key]],
            'protection_scheme' => $encryption->scheme?->value,
            'clear_lead' => $encryption->clearLead,
            'hls_key_uri' => $encryption->keyUri(),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The manifests the export writes, HLS first.
     *
     * @return list<string>
     */
    protected function manifests(): array
    {
        return array_values(array_filter([$this->hlsPlaylist, $this->dashManifest]));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function addInput(StreamType $type, ?string $path, ?string $language, array $options): static
    {
        $media = $path === null || in_array($path, $this->opener->paths(), true)
            ? $this->opener->mediaFor($path)
            : $this->opener->makeMedia($this->opener->disk(), $path);

        $this->inputs[] = [
            'media' => $media,
            'input_type' => 'file',
            'media_type' => $type->value,
            ...($language !== null ? ['language' => $language] : []),
            ...$options,
        ];

        return $this;
    }

    /**
     * @param  array<array-key, string>  $values
     * @return list<string>
     */
    protected function list(string $name, array $values): array
    {
        $values = array_values(array_unique(array_filter(array_map(trim(...), $values), fn (string $value): bool => $value !== '')));

        if ($values === []) {
            throw new InvalidArgumentException("Pass at least one {$name}.");
        }

        return $values;
    }

    /**
     * Apply the defaults from config/streamer.php. Values from .env arrive as strings.
     */
    protected function applyDefaults(): void
    {
        $this->whenConfigured('video_codecs', fn (string $codecs): static => $this->withVideoCodecs(...explode(',', $codecs)));
        $this->whenConfigured('audio_codecs', fn (string $codecs): static => $this->withAudioCodecs(...explode(',', $codecs)));
        $this->whenConfigured('segment_duration', fn (string $seconds): static => $this->segmentDuration((float) $seconds));
        $this->whenConfigured('hardware_acceleration', fn (string $api): static => $this->withHardwareAcceleration($api));
        $this->whenConfigured('ffmpeg_input_args', fn (string $arguments): static => $this->withFFmpegInputArgs($arguments));

        $this->systemBinaries = Config::boolean('streamer.system_binaries', false);
    }

    /**
     * @param  callable(string): mixed  $apply
     */
    protected function whenConfigured(string $key, callable $apply): void
    {
        $value = Config::get("streamer.{$key}");

        if (is_int($value) || is_float($value) || (is_string($value) && trim($value) !== '')) {
            $apply(trim((string) $value));
        }
    }
}

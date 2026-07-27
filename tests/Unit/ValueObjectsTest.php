<?php

declare(strict_types=1);

use Foxws\Streamer\Exceptions\InvalidStreamConfigurationException;
use Foxws\Streamer\Filesystem\Media;
use Foxws\Streamer\Support\CommandBuilder;
use Foxws\Streamer\Support\EncryptionKey;
use Foxws\Streamer\Support\ProtectionScheme;
use Foxws\Streamer\Support\ShakaStreamer;
use Foxws\Streamer\Support\Stream;
use Foxws\Streamer\Support\Streamer;
use Psr\Log\LoggerInterface;

// EncryptionKey

it('formats an encryption key for Shaka', function () {
    $key = new EncryptionKey('69eaa802a6763af979e8d1940fb88392', 'abba271e8bcf552bbd2e86a434a9a5d9');

    expect($key->toShakaFormat())->toBe('label=:key_id=abba271e8bcf552bbd2e86a434a9a5d9:key=69eaa802a6763af979e8d1940fb88392')
        ->and($key->toShakaFormat('SD'))->toBe('label=SD:key_id=abba271e8bcf552bbd2e86a434a9a5d9:key=69eaa802a6763af979e8d1940fb88392');
});

it('converts an encryption key to an array', function () {
    $key = new EncryptionKey('key456', 'keyid123', '/tmp/key');

    expect($key->toArray())->toBe([
        'key' => 'key456',
        'key_id' => 'keyid123',
        'file_path' => '/tmp/key',
    ]);
});

// Stream

it('builds a stream descriptor from a raw array', function () {
    $stream = Stream::fromArray([
        'in' => 'input.mp4',
        'stream' => 'video',
        'output' => 'output.mp4',
        'bandwidth' => '5000000',
    ]);

    expect($stream->getInput())->toBe('input.mp4')
        ->and($stream->getType())->toBe('video')
        ->and($stream->getOutput())->toBe('output.mp4')
        ->and($stream->getOptions())->toBe(['bandwidth' => '5000000']);
});

it('rejects a stream array missing the input field', function () {
    Stream::fromArray(['stream' => 'video', 'output' => 'out.mp4']);
})->throws(InvalidStreamConfigurationException::class, 'Stream configuration missing required field: in');

it('rejects a stream array missing the stream type field', function () {
    Stream::fromArray(['in' => 'in.mp4', 'output' => 'out.mp4']);
})->throws(InvalidStreamConfigurationException::class, 'Stream configuration missing required field: stream');

it('is immutable when adding options', function () {
    $media = mock(Media::class);

    $original = Stream::video($media);
    $withOption = $original->addOption('language', 'en');

    expect($original->getOptions())->toBe([])
        ->and($withOption->getOptions())->toBe(['language' => 'en'])
        ->and($original)->not->toBe($withOption);
});

it('supports subclassing, preserving the subclass through with* methods', function () {
    $media = mock(Media::class);

    $stream = TestSubtitleStream::make($media);
    $withOption = $stream->addOption('language', 'en');

    expect($stream)->toBeInstanceOf(TestSubtitleStream::class)
        ->and($withOption)->toBeInstanceOf(TestSubtitleStream::class)
        ->and($stream->getType())->toBe('text');
});

it('rejects a stream with an invalid type via CommandBuilder', function () {
    CommandBuilder::make()->addStream(Stream::fromArray([
        'in' => 'in.mp4',
        'stream' => 'invalid',
        'output' => 'out.mp4',
    ]));
})->throws(InvalidStreamConfigurationException::class, 'Invalid stream type');

it('rejects a stream without an output via CommandBuilder', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getLocalPath')->andReturn('in.mp4');

    CommandBuilder::make()->addStream(Stream::video($media));
})->throws(InvalidStreamConfigurationException::class, 'Stream configuration missing required field: output');

it('accepts a Stream value object built from Media via CommandBuilder', function () {
    $media = mock(Media::class);
    $media->shouldReceive('getLocalPath')->andReturn('/tmp/in.mp4');

    $stream = Stream::video($media)->setOutput('out.mp4');

    $config = CommandBuilder::make()->addStream($stream)->build();

    expect($config['input_config']['inputs'])->toHaveCount(1)
        ->and($config['input_config']['inputs'][0])->toMatchArray([
            'input_type' => 'file',
            'name' => '/tmp/in.mp4',
            'media_type' => 'video',
        ]);
});

// ProtectionScheme

it('accepts a ProtectionScheme enum for AES encryption', function () {
    $driver = mock(ShakaStreamer::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $streamer = new Streamer($driver, $logger);
    $streamer->withAESEncryption('key', ProtectionScheme::Cbcs);

    expect($streamer->getBuilder()->getOptions()->get('encryption')['protection_scheme'])->toBe('cbcs');
});

it('accepts a raw protection scheme string for AES encryption', function () {
    $driver = mock(ShakaStreamer::class);
    $logger = mock(LoggerInterface::class)->shouldIgnoreMissing();

    $streamer = new Streamer($driver, $logger);
    $streamer->withAESEncryption('key', 'cenc');

    expect($streamer->getBuilder()->getOptions()->get('encryption')['protection_scheme'])->toBe('cenc');
});

/**
 * Regression fixture for the "Custom Streams" extension point documented in
 * docs/ARCHITECTURE.md — Stream's constructor must stay subclassable.
 */
class TestSubtitleStream extends Stream
{
    public static function make(Media $media, string $type = 'text'): self
    {
        return new self($media, null, $type);
    }
}

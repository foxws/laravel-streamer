<?php

declare(strict_types=1);

namespace Foxws\Streamer\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * An AES-128 encryption key pair for Shaka Streamer raw key encryption.
 */
final readonly class EncryptionKey implements Arrayable
{
    public function __construct(
        public string $key,
        public string $keyId,
        public ?string $filePath = null,
    ) {}

    /**
     * Generate a new key/key ID pair without persisting it anywhere.
     */
    public static function generate(): self
    {
        return new self(
            key: EncryptionKeyGenerator::generateKey(),
            keyId: EncryptionKeyGenerator::generateKeyId(),
        );
    }

    /**
     * Generate a new key/key ID pair and write the key to temporary storage.
     */
    public static function generateAndWrite(string $filename = 'encryption.key'): self
    {
        $key = EncryptionKeyGenerator::generateKey();
        $keyId = EncryptionKeyGenerator::generateKeyId();
        $filePath = EncryptionKeyGenerator::writeKeyToTemporary($key, $filename);

        return new self($key, $keyId, $filePath);
    }

    /**
     * Format this key for Shaka Streamer's raw key "keys" config entry.
     */
    public function toShakaFormat(?string $label = null): string
    {
        return EncryptionKeyGenerator::formatForShaka($this->keyId, $this->key, $label);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'key_id' => $this->keyId,
            'file_path' => $this->filePath,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage;

use Farzai\Breaker\Exceptions\StorageReadException;
use Farzai\Breaker\Exceptions\StorageWriteException;

/**
 * JSON-based storage serializer.
 *
 * This serializer converts CircuitState to/from JSON format,
 * with proper error handling for encoding/decoding failures.
 */
class JsonStorageSerializer implements StorageSerializer
{
    /**
     * {@inheritdoc}
     */
    public function serialize(CircuitState $state): string
    {
        try {
            return json_encode($state->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (\JsonException $e) {
            throw new StorageWriteException(
                "Failed to encode circuit state to JSON: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deserialize(string $serviceKey, string $data): CircuitState
    {
        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                throw new StorageReadException('Decoded data is not an array');
            }

            return CircuitState::fromArray($serviceKey, $decoded);
        } catch (\JsonException $e) {
            throw new StorageReadException(
                "Failed to decode circuit state from JSON: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }
}

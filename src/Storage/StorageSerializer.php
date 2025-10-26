<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage;

use Farzai\Breaker\Exceptions\StorageException;

/**
 * Interface for serializing and deserializing circuit state.
 *
 * Design Pattern: Strategy Pattern
 * Purpose: Allow different serialization formats (JSON, msgpack, etc.)
 */
interface StorageSerializer
{
    /**
     * Serialize circuit state to string.
     *
     * @param  CircuitState  $state  State to serialize
     * @return string Serialized data
     *
     * @throws StorageException If serialization fails
     */
    public function serialize(CircuitState $state): string;

    /**
     * Deserialize circuit state from string.
     *
     * @param  string  $serviceKey  Service identifier
     * @param  string  $data  Serialized data
     * @return CircuitState Deserialized state
     *
     * @throws StorageException If deserialization fails
     */
    public function deserialize(string $serviceKey, string $data): CircuitState;
}

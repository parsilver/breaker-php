<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage;

/**
 * Default implementation of CircuitStateRepository.
 *
 * This implementation uses a StorageAdapter for low-level storage
 * and a StorageSerializer for encoding/decoding circuit state.
 */
class DefaultCircuitStateRepository implements CircuitStateRepository
{
    /**
     * Create a new repository instance.
     *
     * @param  StorageAdapter  $adapter  Low-level storage adapter
     * @param  StorageSerializer  $serializer  State serialization handler
     */
    public function __construct(
        private readonly StorageAdapter $adapter,
        private readonly StorageSerializer $serializer,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function find(string $serviceKey): ?CircuitState
    {
        $key = $this->getStorageKey($serviceKey);
        $rawData = $this->adapter->read($key);

        if ($rawData === null) {
            return null;
        }

        return $this->serializer->deserialize($serviceKey, $rawData);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CircuitState $state): void
    {
        $key = $this->getStorageKey($state->serviceKey);
        $rawData = $this->serializer->serialize($state);

        $this->adapter->write($key, $rawData);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $serviceKey): void
    {
        $key = $this->getStorageKey($serviceKey);
        $this->adapter->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $serviceKey): bool
    {
        $key = $this->getStorageKey($serviceKey);

        return $this->adapter->exists($key);
    }

    /**
     * Generate storage key from service key.
     *
     * Uses SHA-256 hashing to prevent service key collisions and
     * ensure filesystem-safe keys.
     *
     * @param  string  $serviceKey  Service identifier
     * @return string Hashed storage key
     */
    protected function getStorageKey(string $serviceKey): string
    {
        // Use SHA-256 hash to prevent collisions and ensure filesystem safety
        // Prefix with "cb_" for clarity and namespace separation
        return 'cb_'.hash('sha256', $serviceKey);
    }
}

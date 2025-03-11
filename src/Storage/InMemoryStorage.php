<?php

namespace Farzai\Breaker\Storage;

class InMemoryStorage implements StorageInterface
{
    protected array $storage = [];

    /**
     * Load the circuit state data.
     */
    public function load(string $serviceKey): ?array
    {
        return $this->storage[$serviceKey] ?? null;
    }

    /**
     * Save the circuit state data.
     */
    public function save(string $serviceKey, array $data): void
    {
        $this->storage[$serviceKey] = $data;
    }
}

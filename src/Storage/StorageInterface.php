<?php

namespace Farzai\Breaker\Storage;

interface StorageInterface
{
    /**
     * Load the circuit state data.
     */
    public function load(string $serviceKey): ?array;

    /**
     * Save the circuit state data.
     */
    public function save(string $serviceKey, array $data): void;
}

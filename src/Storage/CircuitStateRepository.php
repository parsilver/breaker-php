<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage;

use Farzai\Breaker\Exceptions\StorageException;

/**
 * Repository interface for circuit breaker state persistence.
 *
 * Design Pattern: Repository Pattern
 * Purpose: Provide domain-focused abstraction over storage operations
 *
 * This interface separates the domain logic (circuit state) from
 * the underlying storage mechanism, making the code more maintainable
 * and testable.
 */
interface CircuitStateRepository
{
    /**
     * Find circuit state by service key.
     *
     * @param  string  $serviceKey  Service identifier
     * @return CircuitState|null The circuit state or null if not found
     *
     * @throws StorageException If retrieval fails
     */
    public function find(string $serviceKey): ?CircuitState;

    /**
     * Save circuit state.
     *
     * @param  CircuitState  $state  State to persist
     *
     * @throws StorageException If save fails
     */
    public function save(CircuitState $state): void;

    /**
     * Delete circuit state.
     *
     * @param  string  $serviceKey  Service identifier
     *
     * @throws StorageException If deletion fails
     */
    public function delete(string $serviceKey): void;

    /**
     * Check if circuit state exists.
     *
     * @param  string  $serviceKey  Service identifier
     * @return bool True if state exists
     *
     * @throws StorageException If check fails
     */
    public function exists(string $serviceKey): bool;
}

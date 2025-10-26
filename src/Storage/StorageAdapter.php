<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage;

use Farzai\Breaker\Exceptions\StorageException;

/**
 * Low-level storage adapter interface for raw key-value operations.
 *
 * This interface provides the foundation for all storage implementations,
 * offering simple read/write operations that can be decorated with
 * additional functionality like logging, caching, or metrics.
 *
 * Design Pattern: Strategy Pattern
 * Purpose: Allow different storage backends to be swapped transparently
 */
interface StorageAdapter
{
    /**
     * Read raw data from storage.
     *
     * @param  string  $key  Storage key
     * @return string|null Raw data or null if not found
     *
     * @throws StorageException If read operation fails
     */
    public function read(string $key): ?string;

    /**
     * Write raw data to storage.
     *
     * @param  string  $key  Storage key
     * @param  string  $value  Raw data to store
     * @param  int|null  $ttl  Time-to-live in seconds (null = no expiration)
     *
     * @throws StorageException If write operation fails
     */
    public function write(string $key, string $value, ?int $ttl = null): void;

    /**
     * Check if a key exists in storage.
     *
     * @param  string  $key  Storage key
     * @return bool True if key exists, false otherwise
     *
     * @throws StorageException If check operation fails
     */
    public function exists(string $key): bool;

    /**
     * Delete data from storage.
     *
     * @param  string  $key  Storage key
     *
     * @throws StorageException If delete operation fails
     */
    public function delete(string $key): void;

    /**
     * Clear all data from storage (optional operation).
     *
     * @throws StorageException If clear operation fails or is not supported
     */
    public function clear(): void;

    /**
     * Get the adapter name for debugging/logging.
     *
     * @return string Adapter identifier (e.g., "file", "redis", "memory")
     */
    public function getName(): string;
}

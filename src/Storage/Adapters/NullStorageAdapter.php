<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage\Adapters;

use Farzai\Breaker\Storage\StorageAdapter;

/**
 * Null object pattern storage adapter.
 *
 * Design Pattern: Null Object Pattern
 * Purpose: Provide a no-op storage adapter that never fails
 *
 * This adapter:
 * - Never throws exceptions
 * - Always returns null for reads
 * - Silently accepts writes
 * - Useful for testing and disabled storage scenarios
 *
 * When to use:
 * - Testing circuit breaker logic without persistence
 * - Temporarily disabling state persistence
 * - Graceful degradation when storage is unavailable
 */
class NullStorageAdapter implements StorageAdapter
{
    /**
     * {@inheritdoc}
     */
    public function read(string $key): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $key, string $value, ?int $ttl = null): void
    {
        // No-op: silently discard
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        // No-op: nothing to delete
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        // No-op: nothing to clear
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'null';
    }
}

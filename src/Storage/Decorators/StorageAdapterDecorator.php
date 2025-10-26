<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage\Decorators;

use Farzai\Breaker\Storage\StorageAdapter;

/**
 * Base decorator for storage adapters.
 *
 * Design Pattern: Decorator Pattern
 * Purpose: Add functionality to storage adapters without modifying their code
 *
 * This abstract class provides a foundation for creating decorators
 * that wrap storage adapters to add cross-cutting concerns like:
 * - Logging
 * - Metrics collection
 * - Caching
 * - Retry logic
 * - Locking
 */
abstract class StorageAdapterDecorator implements StorageAdapter
{
    /**
     * Create a new storage adapter decorator.
     *
     * @param  StorageAdapter  $adapter  The adapter to decorate
     */
    public function __construct(
        protected readonly StorageAdapter $adapter
    ) {}

    /**
     * {@inheritdoc}
     */
    public function read(string $key): ?string
    {
        return $this->adapter->read($key);
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $key, string $value, ?int $ttl = null): void
    {
        $this->adapter->write($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        return $this->adapter->exists($key);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        $this->adapter->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->adapter->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->adapter->getName();
    }

    /**
     * Get the underlying adapter (useful for testing and debugging).
     *
     * @return StorageAdapter The wrapped adapter
     */
    public function getInnerAdapter(): StorageAdapter
    {
        return $this->adapter;
    }

    /**
     * Get the root adapter by unwrapping all decorators.
     *
     * @return StorageAdapter The root adapter
     */
    public function getRootAdapter(): StorageAdapter
    {
        $adapter = $this->adapter;

        while ($adapter instanceof self) {
            $adapter = $adapter->getInnerAdapter();
        }

        return $adapter;
    }
}

<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage\Adapters;

use Farzai\Breaker\Exceptions\StorageException;
use Farzai\Breaker\Exceptions\StorageReadException;
use Farzai\Breaker\Exceptions\StorageWriteException;
use Farzai\Breaker\Storage\StorageAdapter;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * PSR-16 Simple Cache adapter.
 *
 * Design Pattern: Adapter Pattern
 * Purpose: Integrate any PSR-16 cache implementation with the circuit breaker
 *
 * This adapter allows the circuit breaker to use any PSR-16 compliant
 * cache library (Redis, Memcached, etc.) for state storage.
 *
 * Benefits:
 * - Interoperability with existing cache infrastructure
 * - Access to distributed caching solutions
 * - No need to implement custom Redis/Memcached adapters
 */
class Psr16StorageAdapter implements StorageAdapter
{
    /**
     * Create a new PSR-16 storage adapter.
     *
     * @param  CacheInterface  $cache  PSR-16 cache implementation
     * @param  int|null  $defaultTtl  Default TTL in seconds (null = no expiration)
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ?int $defaultTtl = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function read(string $key): ?string
    {
        try {
            $value = $this->cache->get($key);

            if ($value === null) {
                return null;
            }

            if (! is_string($value)) {
                throw new StorageReadException(
                    'Expected string value from cache, got: '.gettype($value)
                );
            }

            return $value;
        } catch (InvalidArgumentException $e) {
            throw new StorageReadException(
                "Invalid cache key: {$key}",
                (int) $e->getCode(),
                $e
            );
        } catch (\Throwable $e) {
            throw new StorageReadException(
                "Failed to read from cache: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $key, string $value, ?int $ttl = null): void
    {
        try {
            $effectiveTtl = $ttl ?? $this->defaultTtl;

            $success = $this->cache->set($key, $value, $effectiveTtl);

            if (! $success) {
                throw new StorageWriteException("Failed to write to cache: {$key}");
            }
        } catch (InvalidArgumentException $e) {
            throw new StorageWriteException(
                "Invalid cache key or value: {$key}",
                (int) $e->getCode(),
                $e
            );
        } catch (StorageWriteException $e) {
            throw $e; // Re-throw our own exceptions
        } catch (\Throwable $e) {
            throw new StorageWriteException(
                "Failed to write to cache: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        try {
            return $this->cache->has($key);
        } catch (InvalidArgumentException $e) {
            throw new StorageException(
                "Invalid cache key: {$key}",
                (int) $e->getCode(),
                $e
            );
        } catch (\Throwable $e) {
            throw new StorageException(
                "Failed to check cache key existence: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        try {
            $success = $this->cache->delete($key);

            if (! $success) {
                throw new StorageException("Failed to delete from cache: {$key}");
            }
        } catch (InvalidArgumentException $e) {
            throw new StorageException(
                "Invalid cache key: {$key}",
                (int) $e->getCode(),
                $e
            );
        } catch (StorageException $e) {
            throw $e; // Re-throw our own exceptions
        } catch (\Throwable $e) {
            throw new StorageException(
                "Failed to delete from cache: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        try {
            $success = $this->cache->clear();

            if (! $success) {
                throw new StorageException('Failed to clear cache');
            }
        } catch (StorageException $e) {
            throw $e; // Re-throw our own exceptions
        } catch (\Throwable $e) {
            throw new StorageException(
                "Failed to clear cache: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'psr16';
    }

    /**
     * Get the underlying PSR-16 cache instance.
     *
     * @return CacheInterface The cache implementation
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }
}

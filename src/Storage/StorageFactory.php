<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage;

use Farzai\Breaker\Storage\Adapters\FallbackStorageAdapter;
use Farzai\Breaker\Storage\Adapters\FileStorageAdapter;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\Adapters\NullStorageAdapter;
use Farzai\Breaker\Storage\Adapters\Psr16StorageAdapter;
use Farzai\Breaker\Storage\Decorators\LoggingStorageDecorator;
use Farzai\Breaker\Storage\Decorators\MetricsCollector;
use Farzai\Breaker\Storage\Decorators\MetricsStorageDecorator;
use Farzai\Breaker\Storage\Decorators\RetryStorageDecorator;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Factory for creating storage adapters and repositories.
 *
 * Design Pattern: Factory Pattern
 * Purpose: Simplify creation of complex storage configurations
 *
 * This factory provides convenient methods for creating:
 * - Storage adapters (file, memory, PSR-16, null)
 * - Decorated adapters (with logging, metrics, retry)
 * - Repositories (for circuit state persistence)
 * - Fallback chains (for high availability)
 */
class StorageFactory
{
    /**
     * Create a file-based storage adapter.
     *
     * @param  string  $storageDir  Directory for storing files
     * @param  int  $maxTempFileAge  Max age of temp files before cleanup
     */
    public static function file(string $storageDir, int $maxTempFileAge = 3600): FileStorageAdapter
    {
        return new FileStorageAdapter($storageDir, $maxTempFileAge);
    }

    /**
     * Create an in-memory storage adapter.
     */
    public static function memory(): InMemoryStorageAdapter
    {
        return new InMemoryStorageAdapter;
    }

    /**
     * Create a PSR-16 cache adapter.
     *
     * @param  CacheInterface  $cache  PSR-16 cache implementation
     * @param  int|null  $defaultTtl  Default TTL in seconds
     */
    public static function psr16(CacheInterface $cache, ?int $defaultTtl = null): Psr16StorageAdapter
    {
        return new Psr16StorageAdapter($cache, $defaultTtl);
    }

    /**
     * Create a null storage adapter (no-op).
     */
    public static function null(): NullStorageAdapter
    {
        return new NullStorageAdapter;
    }

    /**
     * Create a fallback storage chain.
     *
     * @param  array<StorageAdapter>  $adapters  Adapters in priority order
     * @param  LoggerInterface|null  $logger  Optional logger
     */
    public static function fallback(array $adapters, ?LoggerInterface $logger = null): FallbackStorageAdapter
    {
        return new FallbackStorageAdapter($adapters, $logger);
    }

    /**
     * Decorate adapter with logging.
     *
     * @param  StorageAdapter  $adapter  Adapter to decorate
     * @param  LoggerInterface  $logger  PSR-3 logger
     * @param  string  $successLevel  Log level for success
     * @param  string  $errorLevel  Log level for errors
     */
    public static function withLogging(
        StorageAdapter $adapter,
        LoggerInterface $logger,
        string $successLevel = 'debug',
        string $errorLevel = 'error'
    ): LoggingStorageDecorator {
        return new LoggingStorageDecorator($adapter, $logger, $successLevel, $errorLevel);
    }

    /**
     * Decorate adapter with metrics collection.
     *
     * @param  StorageAdapter  $adapter  Adapter to decorate
     * @param  MetricsCollector  $metrics  Metrics collector
     */
    public static function withMetrics(
        StorageAdapter $adapter,
        MetricsCollector $metrics
    ): MetricsStorageDecorator {
        return new MetricsStorageDecorator($adapter, $metrics);
    }

    /**
     * Decorate adapter with retry logic.
     *
     * @param  StorageAdapter  $adapter  Adapter to decorate
     * @param  int  $maxAttempts  Maximum retry attempts
     * @param  int  $initialDelayMs  Initial delay in ms
     * @param  float  $multiplier  Backoff multiplier
     * @param  bool  $useJitter  Use jitter
     */
    public static function withRetry(
        StorageAdapter $adapter,
        int $maxAttempts = 3,
        int $initialDelayMs = 100,
        float $multiplier = 2.0,
        bool $useJitter = true
    ): RetryStorageDecorator {
        return new RetryStorageDecorator(
            $adapter,
            $maxAttempts,
            $initialDelayMs,
            $multiplier,
            $useJitter
        );
    }

    /**
     * Create a repository with the given adapter.
     *
     * @param  StorageAdapter  $adapter  Storage adapter
     * @param  StorageSerializer|null  $serializer  Optional serializer
     */
    public static function createRepository(
        StorageAdapter $adapter,
        ?StorageSerializer $serializer = null
    ): CircuitStateRepository {
        $serializer = $serializer ?? new JsonStorageSerializer;

        return new DefaultCircuitStateRepository($adapter, $serializer);
    }

    /**
     * Create a fluent builder for complex configurations.
     *
     * @param  StorageAdapter|string  $adapter  Adapter instance or type ('file', 'memory', etc.)
     * @param  array<string, mixed>  $config  Configuration for adapter creation
     */
    public static function builder(StorageAdapter|string $adapter, array $config = []): StorageBuilder
    {
        if (is_string($adapter)) {
            $adapter = match ($adapter) {
                'file' => self::file($config['path'] ?? sys_get_temp_dir()),
                'memory' => self::memory(),
                'null' => self::null(),
                'psr16' => isset($config['cache'])
                    ? self::psr16($config['cache'], $config['ttl'] ?? null)
                    : throw new \InvalidArgumentException('PSR-16 adapter requires cache instance'),
                default => throw new \InvalidArgumentException("Unknown adapter type: {$adapter}"),
            };
        }

        return new StorageBuilder($adapter);
    }
}

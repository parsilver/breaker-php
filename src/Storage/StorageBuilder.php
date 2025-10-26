<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage;

use Farzai\Breaker\Storage\Decorators\LoggingStorageDecorator;
use Farzai\Breaker\Storage\Decorators\MetricsCollector;
use Farzai\Breaker\Storage\Decorators\MetricsStorageDecorator;
use Farzai\Breaker\Storage\Decorators\RetryStorageDecorator;
use Psr\Log\LoggerInterface;

/**
 * Fluent builder for creating decorated storage adapters.
 *
 * Design Pattern: Builder Pattern
 * Purpose: Provide a fluent interface for composing storage decorators
 *
 * Example usage:
 * ```php
 * $storage = StorageFactory::builder('file', ['path' => '/tmp'])
 *     ->withLogging($logger)
 *     ->withMetrics($metrics)
 *     ->withRetry(maxAttempts: 3)
 *     ->build();
 * ```
 */
class StorageBuilder
{
    /**
     * Create a new storage builder.
     *
     * @param  StorageAdapter  $adapter  Base storage adapter
     */
    public function __construct(
        private StorageAdapter $adapter
    ) {}

    /**
     * Add logging decorator.
     *
     * @param  LoggerInterface  $logger  PSR-3 logger
     * @param  string  $successLevel  Log level for success
     * @param  string  $errorLevel  Log level for errors
     */
    public function withLogging(
        LoggerInterface $logger,
        string $successLevel = 'debug',
        string $errorLevel = 'error'
    ): self {
        $this->adapter = new LoggingStorageDecorator(
            $this->adapter,
            $logger,
            $successLevel,
            $errorLevel
        );

        return $this;
    }

    /**
     * Add metrics decorator.
     *
     * @param  MetricsCollector  $metrics  Metrics collector
     */
    public function withMetrics(MetricsCollector $metrics): self
    {
        $this->adapter = new MetricsStorageDecorator($this->adapter, $metrics);

        return $this;
    }

    /**
     * Add retry decorator.
     *
     * @param  int  $maxAttempts  Maximum retry attempts
     * @param  int  $initialDelayMs  Initial delay in ms
     * @param  float  $multiplier  Backoff multiplier
     * @param  bool  $useJitter  Use jitter
     */
    public function withRetry(
        int $maxAttempts = 3,
        int $initialDelayMs = 100,
        float $multiplier = 2.0,
        bool $useJitter = true
    ): self {
        $this->adapter = new RetryStorageDecorator(
            $this->adapter,
            $maxAttempts,
            $initialDelayMs,
            $multiplier,
            $useJitter
        );

        return $this;
    }

    /**
     * Build and return the configured storage adapter.
     */
    public function build(): StorageAdapter
    {
        return $this->adapter;
    }

    /**
     * Build and return a repository with the configured adapter.
     *
     * @param  StorageSerializer|null  $serializer  Optional serializer
     */
    public function buildRepository(?StorageSerializer $serializer = null): CircuitStateRepository
    {
        return StorageFactory::createRepository($this->adapter, $serializer);
    }
}

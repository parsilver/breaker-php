<?php

declare(strict_types=1);

namespace Farzai\Breaker;

use Farzai\Breaker\Config\CircuitBreakerConfig;
use Farzai\Breaker\Contracts\TimeProviderInterface;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\CircuitStateRepository;
use Farzai\Breaker\Storage\DefaultCircuitStateRepository;
use Farzai\Breaker\Storage\JsonStorageSerializer;
use Farzai\Breaker\Time\SystemTimeProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Builder for creating CircuitBreaker instances with a fluent API.
 *
 * This class implements the Builder Pattern to provide a clean and
 * expressive way to configure and create circuit breaker instances.
 *
 * @example
 * ```php
 * $breaker = CircuitBreakerBuilder::create('my-service')
 *     ->withFailureThreshold(5)
 *     ->withTimeout(30)
 *     ->withRepository($repository)
 *     ->withLogger($logger)
 *     ->build();
 * ```
 */
final class CircuitBreakerBuilder
{
    private string $serviceKey;

    private ?CircuitBreakerConfig $config = null;

    private int $failureThreshold = 5;

    private int $successThreshold = 2;

    private int $timeout = 30;

    private int $halfOpenMaxAttempts = 1;

    private ?CircuitStateRepository $repository = null;

    private ?LoggerInterface $logger = null;

    private ?TimeProviderInterface $timeProvider = null;

    /**
     * Private constructor to enforce use of static factory method.
     *
     * @param  string  $serviceKey  Unique identifier for the service
     */
    private function __construct(string $serviceKey)
    {
        $this->serviceKey = $serviceKey;
    }

    /**
     * Create a new builder instance.
     *
     * @param  string  $serviceKey  Unique identifier for the service
     */
    public static function create(string $serviceKey): self
    {
        return new self($serviceKey);
    }

    /**
     * Set the complete configuration object.
     *
     * @param  CircuitBreakerConfig  $config  Configuration object
     */
    public function withConfig(CircuitBreakerConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Set the failure threshold.
     *
     * @param  int  $failureThreshold  Number of failures before opening the circuit
     */
    public function withFailureThreshold(int $failureThreshold): self
    {
        $this->failureThreshold = $failureThreshold;

        return $this;
    }

    /**
     * Set the success threshold.
     *
     * @param  int  $successThreshold  Number of successes needed to close the circuit
     */
    public function withSuccessThreshold(int $successThreshold): self
    {
        $this->successThreshold = $successThreshold;

        return $this;
    }

    /**
     * Set the timeout period.
     *
     * @param  int  $timeout  Seconds to wait before transitioning from open to half-open
     */
    public function withTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Set the half-open max attempts.
     *
     * @param  int  $halfOpenMaxAttempts  Maximum concurrent attempts in half-open state
     */
    public function withHalfOpenMaxAttempts(int $halfOpenMaxAttempts): self
    {
        $this->halfOpenMaxAttempts = $halfOpenMaxAttempts;

        return $this;
    }

    /**
     * Set the repository.
     *
     * @param  CircuitStateRepository  $repository  Repository implementation
     */
    public function withRepository(CircuitStateRepository $repository): self
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * Set the PSR-3 logger.
     *
     * @param  LoggerInterface  $logger  Logger implementation
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Set the time provider.
     *
     * @param  TimeProviderInterface  $timeProvider  Time provider implementation
     */
    public function withTimeProvider(TimeProviderInterface $timeProvider): self
    {
        $this->timeProvider = $timeProvider;

        return $this;
    }

    /**
     * Build the CircuitBreaker instance.
     */
    public function build(): CircuitBreaker
    {
        // Use provided config or build from individual parameters
        $config = $this->config ?? new CircuitBreakerConfig(
            failureThreshold: $this->failureThreshold,
            successThreshold: $this->successThreshold,
            timeout: $this->timeout,
            halfOpenMaxAttempts: $this->halfOpenMaxAttempts,
        );

        // Use provided repository or default to in-memory repository
        $repository = $this->repository ?? new DefaultCircuitStateRepository(
            new InMemoryStorageAdapter,
            new JsonStorageSerializer
        );

        // Use provided logger or default to NullLogger
        $logger = $this->logger ?? new NullLogger;

        // Use provided time provider or default to SystemTimeProvider
        $timeProvider = $this->timeProvider ?? new SystemTimeProvider;

        return new CircuitBreaker(
            serviceKey: $this->serviceKey,
            config: $config,
            repository: $repository,
            logger: $logger,
            timeProvider: $timeProvider,
        );
    }

    /**
     * Quick build with default settings.
     *
     * @param  string  $serviceKey  Service identifier
     */
    public static function default(string $serviceKey): CircuitBreaker
    {
        return self::create($serviceKey)->build();
    }
}

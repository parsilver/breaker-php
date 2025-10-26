<?php

declare(strict_types=1);

namespace Farzai\Breaker;

use Farzai\Breaker\Contracts\TimeProviderInterface;
use Farzai\Breaker\Health\HealthReport;
use Farzai\Breaker\Storage\CircuitStateRepository;
use Psr\Log\LoggerInterface;

/**
 * Manager for circuit breaker instances with service registry.
 *
 * This class implements a registry pattern to manage circuit breaker instances
 * across your application. It automatically creates and caches instances by
 * service key, providing a convenient facade-like experience.
 *
 * @example
 * ```php
 * $manager = new BreakerManager();
 *
 * // Quick one-liner protection
 * $result = $manager->protect('api-service', fn() => callApi());
 *
 * // With fallback
 * $result = $manager->protect(
 *     'api-service',
 *     fn() => callApi(),
 *     fallback: fn($e) => getCachedData()
 * );
 *
 * // Get managed instance for multiple calls
 * $breaker = $manager->instance('api-service', ['failure_threshold' => 3]);
 * ```
 */
class BreakerManager
{
    /**
     * Registry of circuit breaker instances keyed by service name.
     *
     * @var array<string, CircuitBreaker>
     */
    private array $instances = [];

    /**
     * Configuration overrides for specific services.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $configs = [];

    /**
     * Default repository used for all managed instances.
     */
    private ?CircuitStateRepository $defaultRepository = null;

    /**
     * Default logger used for all managed instances.
     */
    private ?LoggerInterface $defaultLogger = null;

    /**
     * Default time provider used for all managed instances.
     */
    private ?TimeProviderInterface $defaultTimeProvider = null;

    /**
     * Create a new breaker manager instance.
     *
     * @param  CircuitStateRepository|null  $defaultRepository  Default repository for all instances
     * @param  LoggerInterface|null  $defaultLogger  Default logger for all instances
     * @param  TimeProviderInterface|null  $defaultTimeProvider  Default time provider for all instances
     */
    public function __construct(
        ?CircuitStateRepository $defaultRepository = null,
        ?LoggerInterface $defaultLogger = null,
        ?TimeProviderInterface $defaultTimeProvider = null
    ) {
        $this->defaultRepository = $defaultRepository;
        $this->defaultLogger = $defaultLogger;
        $this->defaultTimeProvider = $defaultTimeProvider;
    }

    /**
     * Execute a protected callable with automatic circuit breaker management.
     *
     * This is the primary method for one-liner circuit breaker protection.
     * It automatically creates and manages a circuit breaker instance for the
     * specified service.
     *
     * @param  string  $service  Service identifier
     * @param  callable  $callback  The protected operation to execute
     * @param  array<string, mixed>|null  $config  Optional configuration override
     * @param  callable|null  $fallback  Optional fallback function
     * @return mixed The result of the callback or fallback
     *
     * @throws \Throwable
     */
    public function protect(
        string $service,
        callable $callback,
        ?array $config = null,
        ?callable $fallback = null
    ): mixed {
        $breaker = $this->instance($service, $config ?? []);

        if ($fallback !== null) {
            return $breaker->callWithFallback($callback, $fallback);
        }

        return $breaker->call($callback);
    }

    /**
     * Get or create a circuit breaker instance for a service.
     *
     * This method returns a cached instance if one exists, or creates a new one.
     * The instance is stored in the registry for future use.
     *
     * @param  string  $service  Service identifier
     * @param  array<string, mixed>  $config  Configuration for this instance
     */
    public function instance(string $service, array $config = []): CircuitBreaker
    {
        // Check if we already have an instance
        if (isset($this->instances[$service])) {
            return $this->instances[$service];
        }

        // Merge with stored configuration for this service
        $mergedConfig = array_merge(
            $this->configs[$service] ?? [],
            $config
        );

        // Create new instance
        $breaker = new CircuitBreaker(
            serviceKey: $service,
            config: $mergedConfig,
            repository: $this->defaultRepository,
            logger: $this->defaultLogger,
            timeProvider: $this->defaultTimeProvider
        );

        // Store in registry
        $this->instances[$service] = $breaker;

        return $breaker;
    }

    /**
     * Configure default settings for a specific service.
     *
     * This configuration will be used when creating new instances for this service.
     * It does not affect already-created instances.
     *
     * @param  string  $service  Service identifier
     * @param  array<string, mixed>  $config  Configuration settings
     */
    public function configure(string $service, array $config): self
    {
        $this->configs[$service] = $config;

        return $this;
    }

    /**
     * Remove a circuit breaker instance from the registry.
     *
     * @param  string  $service  Service identifier
     * @return bool True if the instance was removed, false if it didn't exist
     */
    public function forget(string $service): bool
    {
        if (isset($this->instances[$service])) {
            unset($this->instances[$service]);

            return true;
        }

        return false;
    }

    /**
     * Clear all circuit breaker instances from the registry.
     *
     * This is useful for testing or resetting state.
     */
    public function flush(): void
    {
        $this->instances = [];
    }

    /**
     * Get all managed circuit breaker instances.
     *
     * @return array<string, CircuitBreaker>
     */
    public function all(): array
    {
        return $this->instances;
    }

    /**
     * Get health report for one or all services.
     *
     * @param  string|null  $service  Service identifier, or null for all services
     * @return HealthReport|array<string, HealthReport>
     */
    public function healthReport(?string $service = null): HealthReport|array
    {
        if ($service !== null) {
            $breaker = $this->instance($service);

            return $breaker->getHealth();
        }

        // Return health for all managed instances
        $reports = [];
        foreach ($this->instances as $serviceName => $breaker) {
            $reports[$serviceName] = $breaker->getHealth();
        }

        return $reports;
    }

    /**
     * Set the default repository for all managed instances.
     *
     * This only affects newly created instances, not existing ones.
     *
     * @param  CircuitStateRepository  $repository  Repository instance
     */
    public function setDefaultRepository(CircuitStateRepository $repository): self
    {
        $this->defaultRepository = $repository;

        return $this;
    }

    /**
     * Set the default logger for all managed instances.
     *
     * This only affects newly created instances, not existing ones.
     *
     * @param  LoggerInterface  $logger  Logger instance
     */
    public function setDefaultLogger(LoggerInterface $logger): self
    {
        $this->defaultLogger = $logger;

        return $this;
    }

    /**
     * Set the default time provider for all managed instances.
     *
     * This only affects newly created instances, not existing ones.
     *
     * @param  TimeProviderInterface  $timeProvider  Time provider instance
     */
    public function setDefaultTimeProvider(TimeProviderInterface $timeProvider): self
    {
        $this->defaultTimeProvider = $timeProvider;

        return $this;
    }
}

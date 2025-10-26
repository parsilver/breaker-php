<?php

declare(strict_types=1);

namespace Farzai\Breaker;

use Farzai\Breaker\Contracts\TimeProviderInterface;
use Farzai\Breaker\Health\HealthReport;
use Farzai\Breaker\Storage\CircuitStateRepository;
use Psr\Log\LoggerInterface;

/**
 * Static facade for circuit breaker operations.
 *
 * This class provides a Laravel-inspired static API for convenient access
 * to circuit breaker functionality. It delegates all calls to an underlying
 * BreakerManager instance.
 *
 * @example
 * ```php
 * // Simple one-liner protection
 * $result = Breaker::protect('api-service', fn() => callApi());
 *
 * // With fallback
 * $result = Breaker::protect(
 *     'api-service',
 *     fn() => callApi(),
 *     fallback: fn($e) => getCachedData()
 * );
 *
 * // Configure a service
 * Breaker::configure('api-service', ['failure_threshold' => 3]);
 *
 * // Get managed instance
 * $breaker = Breaker::instance('api-service');
 * ```
 *
 * @method static mixed protect(string $service, callable $callback, ?array<string, mixed> $config = null, ?callable $fallback = null)
 * @method static CircuitBreaker instance(string $service, array<string, mixed> $config = [])
 * @method static BreakerManager configure(string $service, array<string, mixed> $config)
 * @method static bool forget(string $service)
 * @method static void flush()
 * @method static array<string, CircuitBreaker> all()
 * @method static HealthReport|array<string, HealthReport> healthReport(?string $service = null)
 */
class Breaker
{
    /**
     * The underlying breaker manager instance.
     */
    private static ?BreakerManager $manager = null;

    /**
     * Execute a protected callable with automatic circuit breaker management.
     *
     * @param  string  $service  Service identifier
     * @param  callable  $callback  The protected operation to execute
     * @param  array<string, mixed>|null  $config  Optional configuration override
     * @param  callable|null  $fallback  Optional fallback function
     * @return mixed The result of the callback or fallback
     *
     * @throws \Throwable
     */
    public static function protect(
        string $service,
        callable $callback,
        ?array $config = null,
        ?callable $fallback = null
    ): mixed {
        return static::getManager()->protect($service, $callback, $config, $fallback);
    }

    /**
     * Get or create a circuit breaker instance for a service.
     *
     * @param  string  $service  Service identifier
     * @param  array<string, mixed>  $config  Configuration for this instance
     */
    public static function instance(string $service, array $config = []): CircuitBreaker
    {
        return static::getManager()->instance($service, $config);
    }

    /**
     * Configure default settings for a specific service.
     *
     * @param  string  $service  Service identifier
     * @param  array<string, mixed>  $config  Configuration settings
     */
    public static function configure(string $service, array $config): BreakerManager
    {
        return static::getManager()->configure($service, $config);
    }

    /**
     * Remove a circuit breaker instance from the registry.
     *
     * @param  string  $service  Service identifier
     * @return bool True if the instance was removed, false if it didn't exist
     */
    public static function forget(string $service): bool
    {
        return static::getManager()->forget($service);
    }

    /**
     * Clear all circuit breaker instances from the registry.
     */
    public static function flush(): void
    {
        static::getManager()->flush();
    }

    /**
     * Get all managed circuit breaker instances.
     *
     * @return array<string, CircuitBreaker>
     */
    public static function all(): array
    {
        return static::getManager()->all();
    }

    /**
     * Get health report for one or all services.
     *
     * @param  string|null  $service  Service identifier, or null for all services
     * @return HealthReport|array<string, HealthReport>
     */
    public static function healthReport(?string $service = null): HealthReport|array
    {
        return static::getManager()->healthReport($service);
    }

    /**
     * Set the default repository for all managed instances.
     *
     * @param  CircuitStateRepository  $repository  Repository instance
     */
    public static function setDefaultRepository(CircuitStateRepository $repository): BreakerManager
    {
        return static::getManager()->setDefaultRepository($repository);
    }

    /**
     * Set the default logger for all managed instances.
     *
     * @param  LoggerInterface  $logger  Logger instance
     */
    public static function setDefaultLogger(LoggerInterface $logger): BreakerManager
    {
        return static::getManager()->setDefaultLogger($logger);
    }

    /**
     * Set the default time provider for all managed instances.
     *
     * @param  TimeProviderInterface  $timeProvider  Time provider instance
     */
    public static function setDefaultTimeProvider(TimeProviderInterface $timeProvider): BreakerManager
    {
        return static::getManager()->setDefaultTimeProvider($timeProvider);
    }

    /**
     * Get the underlying manager instance.
     */
    public static function getManager(): BreakerManager
    {
        if (self::$manager === null) {
            self::$manager = new BreakerManager;
        }

        return self::$manager;
    }

    /**
     * Set the manager instance.
     *
     * This is useful for dependency injection and testing.
     *
     * @param  BreakerManager  $manager  Manager instance
     */
    public static function setManager(BreakerManager $manager): void
    {
        self::$manager = $manager;
    }

    /**
     * Clear the manager instance.
     *
     * This is useful for testing to reset state between tests.
     */
    public static function clearManager(): void
    {
        self::$manager = null;
    }
}

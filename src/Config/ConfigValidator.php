<?php

declare(strict_types=1);

namespace Farzai\Breaker\Config;

use InvalidArgumentException;

/**
 * Validator for CircuitBreakerConfig.
 *
 * Ensures all configuration values are valid and within acceptable ranges.
 */
final class ConfigValidator
{
    /**
     * Validate a configuration object.
     *
     * @param  CircuitBreakerConfig  $config  Configuration to validate
     *
     * @throws InvalidArgumentException If any configuration value is invalid
     */
    public static function validate(CircuitBreakerConfig $config): void
    {
        self::validateFailureThreshold($config->failureThreshold);
        self::validateSuccessThreshold($config->successThreshold);
        self::validateTimeout($config->timeout);
        self::validateHalfOpenMaxAttempts($config->halfOpenMaxAttempts);
    }

    /**
     * Validate failure threshold.
     *
     * @param  int  $value  The failure threshold value
     *
     * @throws InvalidArgumentException If the value is invalid
     */
    private static function validateFailureThreshold(int $value): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException(
                "Failure threshold must be at least 1, got {$value}"
            );
        }

        if ($value > 1000) {
            throw new InvalidArgumentException(
                "Failure threshold must be at most 1000, got {$value}"
            );
        }
    }

    /**
     * Validate success threshold.
     *
     * @param  int  $value  The success threshold value
     *
     * @throws InvalidArgumentException If the value is invalid
     */
    private static function validateSuccessThreshold(int $value): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException(
                "Success threshold must be at least 1, got {$value}"
            );
        }

        if ($value > 100) {
            throw new InvalidArgumentException(
                "Success threshold must be at most 100, got {$value}"
            );
        }
    }

    /**
     * Validate timeout.
     *
     * @param  int  $value  The timeout value in seconds
     *
     * @throws InvalidArgumentException If the value is invalid
     */
    private static function validateTimeout(int $value): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException(
                "Timeout must be at least 1 second, got {$value}"
            );
        }

        if ($value > 3600) {
            throw new InvalidArgumentException(
                "Timeout must be at most 3600 seconds (1 hour), got {$value}"
            );
        }
    }

    /**
     * Validate half-open max attempts.
     *
     * @param  int  $value  The half-open max attempts value
     *
     * @throws InvalidArgumentException If the value is invalid
     */
    private static function validateHalfOpenMaxAttempts(int $value): void
    {
        if ($value < 1) {
            throw new InvalidArgumentException(
                "Half-open max attempts must be at least 1, got {$value}"
            );
        }

        if ($value > 10) {
            throw new InvalidArgumentException(
                "Half-open max attempts must be at most 10, got {$value}"
            );
        }
    }
}

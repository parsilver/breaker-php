<?php

declare(strict_types=1);

namespace Farzai\Breaker\Config;

/**
 * Immutable configuration value object for Circuit Breaker.
 *
 * This class represents the configuration settings for a circuit breaker
 * using the Value Object pattern to ensure immutability and validation.
 */
final readonly class CircuitBreakerConfig
{
    /**
     * Create a new configuration instance.
     *
     * @param  int  $failureThreshold  Number of consecutive failures before opening the circuit
     * @param  int  $successThreshold  Number of consecutive successes in half-open state before closing
     * @param  int  $timeout  Seconds to wait before transitioning from open to half-open
     * @param  int  $halfOpenMaxAttempts  Maximum concurrent attempts allowed in half-open state
     *
     * @throws \InvalidArgumentException If any parameter is invalid
     */
    public function __construct(
        public int $failureThreshold = 5,
        public int $successThreshold = 2,
        public int $timeout = 30,
        public int $halfOpenMaxAttempts = 1,
    ) {
        ConfigValidator::validate($this);
    }

    /**
     * Create configuration from array.
     *
     * @param  array<string, mixed>  $options  Configuration options
     */
    public static function fromArray(array $options): self
    {
        return new self(
            failureThreshold: $options['failure_threshold'] ?? 5,
            successThreshold: $options['success_threshold'] ?? 2,
            timeout: $options['timeout'] ?? 30,
            halfOpenMaxAttempts: $options['half_open_max_attempts'] ?? 1,
        );
    }

    /**
     * Convert configuration to array.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'failure_threshold' => $this->failureThreshold,
            'success_threshold' => $this->successThreshold,
            'timeout' => $this->timeout,
            'half_open_max_attempts' => $this->halfOpenMaxAttempts,
        ];
    }

    /**
     * Create a copy with different failure threshold.
     *
     * @param  int  $failureThreshold  New failure threshold
     */
    public function withFailureThreshold(int $failureThreshold): self
    {
        return new self(
            failureThreshold: $failureThreshold,
            successThreshold: $this->successThreshold,
            timeout: $this->timeout,
            halfOpenMaxAttempts: $this->halfOpenMaxAttempts,
        );
    }

    /**
     * Create a copy with different success threshold.
     *
     * @param  int  $successThreshold  New success threshold
     */
    public function withSuccessThreshold(int $successThreshold): self
    {
        return new self(
            failureThreshold: $this->failureThreshold,
            successThreshold: $successThreshold,
            timeout: $this->timeout,
            halfOpenMaxAttempts: $this->halfOpenMaxAttempts,
        );
    }

    /**
     * Create a copy with different timeout.
     *
     * @param  int  $timeout  New timeout in seconds
     */
    public function withTimeout(int $timeout): self
    {
        return new self(
            failureThreshold: $this->failureThreshold,
            successThreshold: $this->successThreshold,
            timeout: $timeout,
            halfOpenMaxAttempts: $this->halfOpenMaxAttempts,
        );
    }

    /**
     * Create a copy with different half-open max attempts.
     *
     * @param  int  $halfOpenMaxAttempts  New half-open max attempts
     */
    public function withHalfOpenMaxAttempts(int $halfOpenMaxAttempts): self
    {
        return new self(
            failureThreshold: $this->failureThreshold,
            successThreshold: $this->successThreshold,
            timeout: $this->timeout,
            halfOpenMaxAttempts: $halfOpenMaxAttempts,
        );
    }
}

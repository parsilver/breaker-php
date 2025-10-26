<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

use Farzai\Breaker\CircuitBreaker;

/**
 * Event dispatched when the circuit breaker transitions to open state.
 *
 * The circuit opens when the failure threshold is reached.
 * In this state, all calls fail fast without executing the protected callable.
 */
final class CircuitOpenedEvent extends AbstractCircuitEvent
{
    /**
     * Create a new circuit opened event.
     *
     * @param  CircuitBreaker  $circuitBreaker  The circuit breaker instance
     * @param  int  $failureCount  The number of failures that triggered opening
     * @param  int  $failureThreshold  The configured failure threshold
     * @param  int  $timeout  Seconds until the circuit will transition to half-open
     * @param  int  $timestamp  Unix timestamp when the event occurred
     */
    public function __construct(
        CircuitBreaker $circuitBreaker,
        private readonly int $failureCount,
        private readonly int $failureThreshold,
        private readonly int $timeout,
        int $timestamp = 0
    ) {
        parent::__construct($circuitBreaker, $timestamp);
    }

    /**
     * Get the failure count when the circuit opened.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get the configured failure threshold.
     */
    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    /**
     * Get the timeout in seconds until half-open state.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get the timestamp when the circuit will transition to half-open.
     */
    public function getHalfOpenTimestamp(): int
    {
        return $this->getTimestamp() + $this->timeout;
    }

    /**
     * Get a string representation of the event.
     */
    public function __toString(): string
    {
        return sprintf(
            'CircuitOpenedEvent[service=%s, failures=%d/%d, timeout=%ds, timestamp=%d]',
            $this->getServiceKey(),
            $this->failureCount,
            $this->failureThreshold,
            $this->timeout,
            $this->getTimestamp()
        );
    }
}

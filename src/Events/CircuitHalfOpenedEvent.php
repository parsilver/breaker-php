<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

use Farzai\Breaker\CircuitBreaker;

/**
 * Event dispatched when the circuit breaker transitions to half-open state.
 *
 * The circuit transitions to half-open when:
 * - The timeout expires after opening
 * - The circuit is manually set to half-open
 *
 * In this state, the circuit allows a limited number of test calls to check
 * if the protected service has recovered.
 */
final class CircuitHalfOpenedEvent extends AbstractCircuitEvent
{
    /**
     * Create a new circuit half-opened event.
     *
     * @param  CircuitBreaker  $circuitBreaker  The circuit breaker instance
     * @param  int  $successThreshold  Number of successes needed to close the circuit
     * @param  int  $timestamp  Unix timestamp when the event occurred
     */
    public function __construct(
        CircuitBreaker $circuitBreaker,
        private readonly int $successThreshold,
        int $timestamp = 0
    ) {
        parent::__construct($circuitBreaker, $timestamp);
    }

    /**
     * Get the success threshold required to close the circuit.
     */
    public function getSuccessThreshold(): int
    {
        return $this->successThreshold;
    }

    /**
     * Get a string representation of the event.
     */
    public function __toString(): string
    {
        return sprintf(
            'CircuitHalfOpenedEvent[service=%s, successThreshold=%d, timestamp=%d]',
            $this->getServiceKey(),
            $this->successThreshold,
            $this->getTimestamp()
        );
    }
}

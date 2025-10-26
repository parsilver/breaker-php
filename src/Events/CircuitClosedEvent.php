<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

use Farzai\Breaker\CircuitBreaker;

/**
 * Event dispatched when the circuit breaker transitions to closed state.
 *
 * The circuit closes when:
 * - The success threshold is reached in half-open state
 * - The circuit is manually reset
 *
 * In this state, all calls are executed normally.
 */
final class CircuitClosedEvent extends AbstractCircuitEvent
{
    /**
     * Create a new circuit closed event.
     *
     * @param  CircuitBreaker  $circuitBreaker  The circuit breaker instance
     * @param  string  $previousState  The state before closing (typically 'half-open')
     * @param  int  $timestamp  Unix timestamp when the event occurred
     */
    public function __construct(
        CircuitBreaker $circuitBreaker,
        private readonly string $previousState,
        int $timestamp = 0
    ) {
        parent::__construct($circuitBreaker, $timestamp);
    }

    /**
     * Get the previous state before closing.
     */
    public function getPreviousState(): string
    {
        return $this->previousState;
    }

    /**
     * Check if the circuit recovered from open state.
     */
    public function isRecovery(): bool
    {
        return $this->previousState === 'half-open';
    }

    /**
     * Get a string representation of the event.
     */
    public function __toString(): string
    {
        return sprintf(
            'CircuitClosedEvent[service=%s, from=%s, timestamp=%d]',
            $this->getServiceKey(),
            $this->previousState,
            $this->getTimestamp()
        );
    }
}

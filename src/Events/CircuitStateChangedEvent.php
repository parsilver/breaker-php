<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

use Farzai\Breaker\CircuitBreaker;

/**
 * Event dispatched when the circuit breaker transitions between states.
 *
 * This is a general event fired for all state transitions.
 * Specific events (CircuitOpenedEvent, CircuitClosedEvent, etc.) are also fired.
 */
final class CircuitStateChangedEvent extends AbstractCircuitEvent
{
    /**
     * Create a new circuit state changed event.
     *
     * @param  CircuitBreaker  $circuitBreaker  The circuit breaker instance
     * @param  string  $previousState  The previous state (closed, open, half-open)
     * @param  string  $newState  The new state (closed, open, half-open)
     * @param  int  $timestamp  Unix timestamp when the event occurred
     */
    public function __construct(
        CircuitBreaker $circuitBreaker,
        private readonly string $previousState,
        private readonly string $newState,
        int $timestamp = 0
    ) {
        parent::__construct($circuitBreaker, $timestamp);
    }

    /**
     * Get the previous state before the transition.
     */
    public function getPreviousState(): string
    {
        return $this->previousState;
    }

    /**
     * Get the new state after the transition.
     */
    public function getNewState(): string
    {
        return $this->newState;
    }

    /**
     * Check if this is a transition to open state.
     */
    public function isTransitionToOpen(): bool
    {
        return $this->newState === 'open';
    }

    /**
     * Check if this is a transition to closed state.
     */
    public function isTransitionToClosed(): bool
    {
        return $this->newState === 'closed';
    }

    /**
     * Check if this is a transition to half-open state.
     */
    public function isTransitionToHalfOpen(): bool
    {
        return $this->newState === 'half-open';
    }

    /**
     * Get a string representation of the event.
     */
    public function __toString(): string
    {
        return sprintf(
            'CircuitStateChangedEvent[service=%s, %s->%s, timestamp=%d]',
            $this->getServiceKey(),
            $this->previousState,
            $this->newState,
            $this->getTimestamp()
        );
    }
}

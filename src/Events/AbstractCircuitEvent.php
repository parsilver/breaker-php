<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

use Farzai\Breaker\CircuitBreaker;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base class for all circuit breaker events.
 *
 * Provides common functionality and ensures all events are immutable.
 */
abstract class AbstractCircuitEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    /**
     * Unix timestamp when the event occurred.
     */
    private readonly int $timestamp;

    /**
     * Create a new circuit event.
     *
     * @param  CircuitBreaker  $circuitBreaker  The circuit breaker instance
     * @param  int  $timestamp  Unix timestamp when the event occurred (0 = use current time)
     */
    public function __construct(
        private readonly CircuitBreaker $circuitBreaker,
        int $timestamp = 0
    ) {
        $this->timestamp = $timestamp ?: time();
    }

    /**
     * Get the circuit breaker instance.
     */
    public function getCircuitBreaker(): CircuitBreaker
    {
        return $this->circuitBreaker;
    }

    /**
     * Get the timestamp when the event occurred.
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Get the service key from the circuit breaker.
     */
    public function getServiceKey(): string
    {
        return $this->circuitBreaker->getServiceKey();
    }

    /**
     * Get the current circuit state.
     */
    public function getCurrentState(): string
    {
        return $this->circuitBreaker->getState();
    }

    /**
     * Stop event propagation to subsequent listeners.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Check if event propagation has been stopped.
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Get a string representation of the event.
     */
    public function __toString(): string
    {
        return sprintf(
            '%s[service=%s, state=%s, timestamp=%d]',
            static::class,
            $this->getServiceKey(),
            $this->getCurrentState(),
            $this->getTimestamp()
        );
    }
}

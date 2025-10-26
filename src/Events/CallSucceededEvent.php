<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

use Farzai\Breaker\CircuitBreaker;

/**
 * Event dispatched when a protected call executes successfully.
 *
 * This event is fired after the callable completes without throwing an exception.
 */
final class CallSucceededEvent extends AbstractCircuitEvent
{
    /**
     * Create a new call succeeded event.
     *
     * @param  CircuitBreaker  $circuitBreaker  The circuit breaker instance
     * @param  mixed  $result  The result returned by the callable
     * @param  float  $executionTime  Execution time in milliseconds
     * @param  int  $timestamp  Unix timestamp when the event occurred
     */
    public function __construct(
        CircuitBreaker $circuitBreaker,
        private readonly mixed $result,
        private readonly float $executionTime = 0.0,
        int $timestamp = 0
    ) {
        parent::__construct($circuitBreaker, $timestamp);
    }

    /**
     * Get the result returned by the callable.
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * Get the execution time in milliseconds.
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Get a string representation of the event.
     */
    public function __toString(): string
    {
        return sprintf(
            'CallSucceededEvent[service=%s, state=%s, executionTime=%.2fms, timestamp=%d]',
            $this->getServiceKey(),
            $this->getCurrentState(),
            $this->executionTime,
            $this->getTimestamp()
        );
    }
}

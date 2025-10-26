<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

use Farzai\Breaker\CircuitBreaker;
use Throwable;

/**
 * Event dispatched when a protected call fails with an exception.
 *
 * This event is fired when the callable throws an exception.
 */
final class CallFailedEvent extends AbstractCircuitEvent
{
    /**
     * Create a new call failed event.
     *
     * @param  CircuitBreaker  $circuitBreaker  The circuit breaker instance
     * @param  Throwable  $exception  The exception that was thrown
     * @param  float  $executionTime  Execution time in milliseconds before failure
     * @param  int  $timestamp  Unix timestamp when the event occurred
     */
    public function __construct(
        CircuitBreaker $circuitBreaker,
        private readonly Throwable $exception,
        private readonly float $executionTime = 0.0,
        int $timestamp = 0
    ) {
        parent::__construct($circuitBreaker, $timestamp);
    }

    /**
     * Get the exception that was thrown.
     */
    public function getException(): Throwable
    {
        return $this->exception;
    }

    /**
     * Get the exception message.
     */
    public function getExceptionMessage(): string
    {
        return $this->exception->getMessage();
    }

    /**
     * Get the exception class name.
     */
    public function getExceptionClass(): string
    {
        return $this->exception::class;
    }

    /**
     * Get the execution time in milliseconds before failure.
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Get the current failure count after this failure.
     */
    public function getFailureCount(): int
    {
        return $this->getCircuitBreaker()->getFailureCount();
    }

    /**
     * Get the failure threshold.
     */
    public function getFailureThreshold(): int
    {
        return $this->getCircuitBreaker()->getFailureThreshold();
    }

    /**
     * Check if this failure will trigger the circuit to open.
     */
    public function willTriggerOpen(): bool
    {
        return $this->getFailureCount() >= $this->getFailureThreshold()
            && $this->getCurrentState() === 'closed';
    }

    /**
     * Get a string representation of the event.
     */
    public function __toString(): string
    {
        return sprintf(
            'CallFailedEvent[service=%s, state=%s, exception=%s, failures=%d/%d, timestamp=%d]',
            $this->getServiceKey(),
            $this->getCurrentState(),
            $this->getExceptionClass(),
            $this->getFailureCount(),
            $this->getFailureThreshold(),
            $this->getTimestamp()
        );
    }
}

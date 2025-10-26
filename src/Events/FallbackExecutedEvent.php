<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

use Farzai\Breaker\CircuitBreaker;
use Throwable;

/**
 * Event dispatched when a fallback is executed.
 *
 * This event is fired when the primary callable fails and a fallback is provided.
 */
final class FallbackExecutedEvent extends AbstractCircuitEvent
{
    /**
     * Create a new fallback executed event.
     *
     * @param  CircuitBreaker  $circuitBreaker  The circuit breaker instance
     * @param  mixed  $result  The result returned by the fallback
     * @param  Throwable  $originalException  The exception from the primary callable
     * @param  float  $executionTime  Fallback execution time in milliseconds
     * @param  int  $timestamp  Unix timestamp when the event occurred
     */
    public function __construct(
        CircuitBreaker $circuitBreaker,
        private readonly mixed $result,
        private readonly Throwable $originalException,
        private readonly float $executionTime = 0.0,
        int $timestamp = 0
    ) {
        parent::__construct($circuitBreaker, $timestamp);
    }

    /**
     * Get the result returned by the fallback.
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * Get the original exception from the primary callable.
     */
    public function getOriginalException(): Throwable
    {
        return $this->originalException;
    }

    /**
     * Get the original exception message.
     */
    public function getOriginalExceptionMessage(): string
    {
        return $this->originalException->getMessage();
    }

    /**
     * Get the fallback execution time in milliseconds.
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
            'FallbackExecutedEvent[service=%s, state=%s, originalException=%s, executionTime=%.2fms, timestamp=%d]',
            $this->getServiceKey(),
            $this->getCurrentState(),
            $this->originalException::class,
            $this->executionTime,
            $this->getTimestamp()
        );
    }
}

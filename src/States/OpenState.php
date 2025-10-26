<?php

declare(strict_types=1);

namespace Farzai\Breaker\States;

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;

class OpenState implements StateInterface
{
    /**
     * Get the name of the state.
     */
    public function getName(): string
    {
        return 'open';
    }

    /**
     * Execute a protected callable.
     *
     * @throws CircuitOpenException
     */
    public function call(CircuitBreaker $circuitBreaker, callable $callable): mixed
    {
        // Check if timeout has been reached using TimeProvider for testability
        if ($this->isTimeoutReached($circuitBreaker)) {
            // Transition to half-open state before executing the call
            // This prevents race conditions by ensuring state is updated before execution
            $circuitBreaker->halfOpen();

            // Delegate to circuit breaker's call method which will use the new HalfOpenState
            // This is safe because we've already transitioned to half-open
            return $circuitBreaker->call($callable);
        }

        // Circuit is still open and timeout not reached - fail fast
        throw new CircuitOpenException($circuitBreaker);
    }

    /**
     * Report success.
     */
    public function reportSuccess(CircuitBreaker $circuitBreaker): void
    {
        // Do nothing in open state
    }

    /**
     * Report failure.
     */
    public function reportFailure(CircuitBreaker $circuitBreaker): void
    {
        // Do nothing in open state
    }

    /**
     * Check if the timeout has been reached.
     *
     * Uses the TimeProvider from CircuitBreaker for testability and consistency.
     */
    private function isTimeoutReached(CircuitBreaker $circuitBreaker): bool
    {
        $currentTime = $circuitBreaker->getTimeProvider()->getCurrentTime();
        $timeOpen = $currentTime - $circuitBreaker->getLastFailureTime();

        return $timeOpen >= $circuitBreaker->getTimeout();
    }
}

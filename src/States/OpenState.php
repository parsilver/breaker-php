<?php

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
     *
     * @throws CircuitOpenException
     */
    public function call(CircuitBreaker $circuitBreaker, callable $callable): mixed
    {
        if ($this->isTimeoutReached($circuitBreaker)) {
            $circuitBreaker->halfOpen();

            return $circuitBreaker->call($callable);
        }

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
     */
    private function isTimeoutReached(CircuitBreaker $circuitBreaker): bool
    {
        $timeOpen = time() - $circuitBreaker->getLastFailureTime();

        return $timeOpen >= $circuitBreaker->getTimeout();
    }
}

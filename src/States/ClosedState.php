<?php

namespace Farzai\Breaker\States;

use Farzai\Breaker\CircuitBreaker;

class ClosedState implements StateInterface
{
    /**
     * Get the name of the state.
     */
    public function getName(): string
    {
        return 'closed';
    }

    /**
     * Execute a protected callable.
     */
    public function call(CircuitBreaker $circuitBreaker, callable $callable): mixed
    {
        try {
            $result = $callable();
            $this->reportSuccess($circuitBreaker);

            return $result;
        } catch (\Throwable $e) {
            $this->reportFailure($circuitBreaker);

            throw $e;
        }
    }

    /**
     * Report success.
     */
    public function reportSuccess(CircuitBreaker $circuitBreaker): void
    {
        $circuitBreaker->resetFailureCount();
    }

    /**
     * Report failure.
     */
    public function reportFailure(CircuitBreaker $circuitBreaker): void
    {
        $circuitBreaker->incrementFailureCount();

        if ($circuitBreaker->getFailureCount() >= $circuitBreaker->getFailureThreshold()) {
            $circuitBreaker->open();
        }
    }
}

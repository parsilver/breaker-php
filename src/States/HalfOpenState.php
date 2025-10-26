<?php

declare(strict_types=1);

namespace Farzai\Breaker\States;

use Farzai\Breaker\CircuitBreaker;

class HalfOpenState implements StateInterface
{
    /**
     * Get the name of the state.
     */
    public function getName(): string
    {
        return 'half-open';
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
        $circuitBreaker->incrementSuccessCount();

        if ($circuitBreaker->getSuccessCount() >= $circuitBreaker->getSuccessThreshold()) {
            $circuitBreaker->close();
        }
    }

    /**
     * Report failure.
     */
    public function reportFailure(CircuitBreaker $circuitBreaker): void
    {
        $circuitBreaker->resetSuccessCount();
        $circuitBreaker->open();
    }
}

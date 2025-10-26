<?php

declare(strict_types=1);

namespace Farzai\Breaker\States;

use Farzai\Breaker\CircuitBreaker;

interface StateInterface
{
    /**
     * Get the name of the state.
     */
    public function getName(): string;

    /**
     * Execute a protected callable.
     */
    public function call(CircuitBreaker $circuitBreaker, callable $callable): mixed;

    /**
     * Report success.
     */
    public function reportSuccess(CircuitBreaker $circuitBreaker): void;

    /**
     * Report failure.
     */
    public function reportFailure(CircuitBreaker $circuitBreaker): void;
}

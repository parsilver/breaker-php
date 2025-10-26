<?php

declare(strict_types=1);

namespace Farzai\Breaker\Exceptions;

use Farzai\Breaker\CircuitBreaker;
use RuntimeException;

class CircuitBreakerException extends RuntimeException
{
    protected CircuitBreaker $circuitBreaker;

    /**
     * Create a new exception instance.
     */
    public function __construct(
        CircuitBreaker $circuitBreaker,
        string $message = 'Circuit breaker exception',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->circuitBreaker = $circuitBreaker;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the circuit breaker instance.
     */
    public function getCircuitBreaker(): CircuitBreaker
    {
        return $this->circuitBreaker;
    }
}

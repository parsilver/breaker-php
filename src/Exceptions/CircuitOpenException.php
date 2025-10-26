<?php

declare(strict_types=1);

namespace Farzai\Breaker\Exceptions;

use Farzai\Breaker\CircuitBreaker;

class CircuitOpenException extends CircuitBreakerException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        CircuitBreaker $circuitBreaker,
        string $message = 'Circuit is open',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($circuitBreaker, $message, $code, $previous);
    }
}

<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitBreakerException;

// Test CircuitBreakerException functionality
test('circuit breaker exception stores and returns circuit breaker instance', function () {
    // Create a circuit breaker
    $circuitBreaker = new CircuitBreaker('test-service');
    
    // Create an exception with the circuit breaker
    $exception = new CircuitBreakerException($circuitBreaker);
    
    // Verify the exception stores and returns the circuit breaker
    expect($exception->getCircuitBreaker())->toBe($circuitBreaker);
});

test('circuit breaker exception can be created with custom message and code', function () {
    // Create a circuit breaker
    $circuitBreaker = new CircuitBreaker('test-service');
    
    // Custom message and code
    $message = 'Custom exception message';
    $code = 123;
    
    // Create an exception with custom message and code
    $exception = new CircuitBreakerException($circuitBreaker, $message, $code);
    
    // Verify the exception properties
    expect($exception->getMessage())->toBe($message);
    expect($exception->getCode())->toBe($code);
    expect($exception->getCircuitBreaker())->toBe($circuitBreaker);
});

test('circuit breaker exception can be created with previous exception', function () {
    // Create a circuit breaker
    $circuitBreaker = new CircuitBreaker('test-service');
    
    // Create a previous exception
    $previousException = new \Exception('Previous exception');
    
    // Create an exception with a previous exception
    $exception = new CircuitBreakerException(
        $circuitBreaker,
        'Main exception',
        0,
        $previousException
    );
    
    // Verify the previous exception is stored
    expect($exception->getPrevious())->toBe($previousException);
}); 
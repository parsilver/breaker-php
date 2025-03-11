<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;

// Test circuit breaker with fallback for successful calls
test('circuit breaker with fallback for successful calls', function () {
    // Create a new circuit breaker instance
    $breaker = new CircuitBreaker('test-service');

    // Execute a function through the circuit breaker with fallback
    $result = $breaker->callWithFallback(
        function () {
            return 'success';
        },
        function () {
            return 'fallback';
        }
    );

    // Assert the primary function executed correctly
    expect($result)->toBe('success');
    expect($breaker->getState())->toBe('closed');
});

// Test circuit breaker with fallback when primary function fails
test('circuit breaker with fallback when primary function fails', function () {
    // Create a new circuit breaker instance
    $breaker = new CircuitBreaker('test-service');

    // Execute a function that fails and triggers the fallback
    $result = $breaker->callWithFallback(
        function () {
            throw new \Exception('Service error');
        },
        function ($exception) {
            expect($exception->getMessage())->toBe('Service error');

            return 'fallback';
        }
    );

    // Assert the fallback was used instead
    expect($result)->toBe('fallback');
    expect($breaker->getFailureCount())->toBe(1);
});

// Test circuit breaker with fallback when circuit is open
test('circuit breaker with fallback when circuit is open', function () {
    // Create a circuit breaker with a failure threshold of 1
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
    ]);

    // Trip the breaker
    try {
        $breaker->call(function () {
            throw new \Exception('Service error');
        });
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Service error');
    }

    // Assert circuit is open
    expect($breaker->getState())->toBe('open');

    // Try to call with fallback when circuit is open
    $result = $breaker->callWithFallback(
        function () {
            return 'success'; // This should not execute
        },
        function ($exception) {
            expect($exception)->toBeInstanceOf(CircuitOpenException::class);

            return 'fallback when open';
        }
    );

    // Assert fallback was called due to open circuit
    expect($result)->toBe('fallback when open');
});

// Test circuit breaker fallback behavior with circuit transitions
test('circuit breaker fallback behavior with circuit transitions', function () {
    // Create a circuit breaker with longer timeout to ensure it stays open during test
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
        'success_threshold' => 2,
        'timeout' => 30, // Use a longer timeout to ensure it stays open
    ]);

    // Trip the breaker
    try {
        $breaker->call(function () {
            throw new \Exception('Service error');
        });
    } catch (\Exception $e) {
        // Expected exception
    }

    // Assert circuit is open
    expect($breaker->getState())->toBe('open');

    // Verify that using the fallback doesn't affect the circuit state
    $result = $breaker->callWithFallback(
        function () {
            return 'primary'; // This should not execute because circuit is open
        },
        function ($exception) {
            expect($exception)->toBeInstanceOf(\Farzai\Breaker\Exceptions\CircuitOpenException::class);

            return 'fallback';
        }
    );

    expect($result)->toBe('fallback');
    expect($breaker->getState())->toBe('open');

    // Force the circuit to half-open state
    $breaker->halfOpen();

    // Successful call in half-open state should increment success count
    $breaker->call(function () {
        return 'success';
    });

    // Verify success was counted
    expect($breaker->getState())->toBe('half-open');
    expect($breaker->getSuccessCount())->toBe(1);
});

// Test that fallback can receive both the exception and circuit breaker
test('fallback receives exception and circuit breaker', function () {
    $breaker = new CircuitBreaker('test-service');

    $result = $breaker->callWithFallback(
        function () {
            throw new \Exception('Service error');
        },
        function ($exception, $circuitBreaker) {
            expect($exception)->toBeInstanceOf(\Exception::class);
            expect($circuitBreaker)->toBeInstanceOf(CircuitBreaker::class);

            return 'fallback with context';
        }
    );

    expect($result)->toBe('fallback with context');
});

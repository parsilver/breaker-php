<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;

// Test circuit breaker is initially in closed state
test('circuit breaker is initially in closed state', function () {
    // Create a new circuit breaker instance
    $breaker = new CircuitBreaker('test-service');

    // Assert that the initial state is closed
    expect($breaker->getState())->toBe('closed');
});

// Test circuit breaker calls work normally
test('circuit breaker calls work normally', function () {
    // Create a new circuit breaker instance
    $breaker = new CircuitBreaker('test-service');

    // Execute a function through the circuit breaker
    $result = $breaker->call(function () {
        return 'success';
    });

    // Assert the function executed correctly and state remains closed
    expect($result)->toBe('success');
    expect($breaker->getState())->toBe('closed');
});

// Test circuit breaker opens after failure threshold
test('circuit breaker opens after failure threshold', function () {
    // Create a circuit breaker with a failure threshold of 2
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 2,
    ]);

    // First failure
    try {
        $breaker->call(function () {
            throw new \Exception('Service error');
        });
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Service error');
    }

    // Assert circuit remains closed after first failure
    expect($breaker->getState())->toBe('closed');

    // Second failure - should trip the breaker
    try {
        $breaker->call(function () {
            throw new \Exception('Service error');
        });
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Service error');
    }

    // Assert circuit is now open
    expect($breaker->getState())->toBe('open');
});

// Test circuit breaker fails fast when open
test('circuit breaker fails fast when open', function () {
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

    // Next call should fail fast with CircuitOpenException
    expect(fn () => $breaker->call(fn () => 'success'))
        ->toThrow(CircuitOpenException::class);
});

// Test circuit breaker transitions to half-open after timeout
test('circuit breaker transitions to half-open after timeout', function () {
    // Create a circuit breaker with no waiting time
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
        'timeout' => 0, // No waiting time for this test
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

    // Should transition to half-open on the next call after timeout
    $result = $breaker->call(function () {
        return 'success';
    });

    // Assert successful call and half-open state
    expect($result)->toBe('success');
    expect($breaker->getState())->toBe('half-open');
});

// Test circuit breaker closes after success threshold
test('circuit breaker closes after success threshold', function () {
    // Create a circuit breaker with success threshold of 2
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
        'success_threshold' => 2,
        'timeout' => 0, // No waiting time for this test
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

    // First success in half-open state
    $breaker->call(function () {
        return 'success';
    });

    // Assert circuit is half-open
    expect($breaker->getState())->toBe('half-open');

    // Second success should close the circuit
    $breaker->call(function () {
        return 'success';
    });

    // Assert circuit is closed
    expect($breaker->getState())->toBe('closed');
});

// Test circuit breaker reopens on failure in half-open state
test('circuit breaker reopens on failure in half-open state', function () {
    // Create a circuit breaker with no waiting time
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
        'timeout' => 0, // No waiting time for this test
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

    // Move to half-open state
    $breaker->call(function () {
        return 'success';
    });

    // Assert circuit is half-open
    expect($breaker->getState())->toBe('half-open');

    // Fail in half-open state should re-open the circuit
    try {
        $breaker->call(function () {
            throw new \Exception('Service error again');
        });
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Service error again');
    }

    // Assert circuit is open again
    expect($breaker->getState())->toBe('open');
});

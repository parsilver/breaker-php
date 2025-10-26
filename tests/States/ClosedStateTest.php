<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\States\ClosedState;

// Test ClosedState functionality
test('closed state getName returns correct state name', function () {
    $closedState = new ClosedState;
    expect($closedState->getName())->toBe('closed');
});

test('closed state call executes successfully and resets failure count', function () {
    // Create a circuit breaker in closed state
    $circuitBreaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 3,
    ]);

    // Set some failures first
    $circuitBreaker->incrementFailureCount();
    $circuitBreaker->incrementFailureCount();
    expect($circuitBreaker->getFailureCount())->toBe(2);

    // Create a ClosedState instance
    $closedState = new ClosedState;

    // Call should succeed and reset failure count
    $callable = function () {
        return 'success';
    };

    $result = $closedState->call($circuitBreaker, $callable);

    expect($result)->toBe('success');
    expect($circuitBreaker->getFailureCount())->toBe(0);
});

test('closed state call handles failure and increments failure count', function () {
    // Create a circuit breaker in closed state
    $circuitBreaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 5,
    ]);

    expect($circuitBreaker->getFailureCount())->toBe(0);

    // Create a ClosedState instance
    $closedState = new ClosedState;

    // Callable that throws exception
    $callable = function () {
        throw new Exception('Test failure');
    };

    // Call should throw exception and increment failure count
    try {
        $closedState->call($circuitBreaker, $callable);
        expect(false)->toBeTrue(); // Should not reach here
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Test failure');
    }

    expect($circuitBreaker->getFailureCount())->toBe(1);
});

test('closed state transitions to open when failure threshold reached', function () {
    // Create a circuit breaker with low failure threshold
    $circuitBreaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 3,
    ]);

    // Set failures to threshold - 1
    $circuitBreaker->incrementFailureCount();
    $circuitBreaker->incrementFailureCount();
    expect($circuitBreaker->getFailureCount())->toBe(2);
    expect($circuitBreaker->getState())->toBe('closed');

    // Create a ClosedState instance
    $closedState = new ClosedState;

    // Callable that throws exception
    $callable = function () {
        throw new Exception('Final failure');
    };

    // This failure should trigger the circuit to open
    try {
        $closedState->call($circuitBreaker, $callable);
    } catch (Exception $e) {
        // Expected
    }

    // Verify circuit is now open
    expect($circuitBreaker->getState())->toBe('open');
    expect($circuitBreaker->getFailureCount())->toBe(3);
});

test('closed state reportSuccess resets failure count', function () {
    $closedState = new ClosedState;

    // Create a circuit breaker
    $circuitBreaker = new CircuitBreaker('test-service');

    // Set some failures
    $circuitBreaker->incrementFailureCount();
    $circuitBreaker->incrementFailureCount();
    expect($circuitBreaker->getFailureCount())->toBe(2);

    // Call reportSuccess
    $closedState->reportSuccess($circuitBreaker);

    // Verify failure count is reset
    expect($circuitBreaker->getFailureCount())->toBe(0);
});

test('closed state reportFailure increments failure count', function () {
    $closedState = new ClosedState;

    // Create a circuit breaker
    $circuitBreaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 10,
    ]);

    expect($circuitBreaker->getFailureCount())->toBe(0);

    // Call reportFailure
    $closedState->reportFailure($circuitBreaker);

    expect($circuitBreaker->getFailureCount())->toBe(1);
});

test('closed state handles multiple successful calls', function () {
    $circuitBreaker = new CircuitBreaker('test-service');
    $closedState = new ClosedState;

    $callCount = 0;
    $callable = function () use (&$callCount) {
        $callCount++;

        return 'success';
    };

    // Make multiple successful calls
    for ($i = 0; $i < 5; $i++) {
        $result = $closedState->call($circuitBreaker, $callable);
        expect($result)->toBe('success');
    }

    expect($callCount)->toBe(5);
    expect($circuitBreaker->getFailureCount())->toBe(0);
    expect($circuitBreaker->getState())->toBe('closed');
});

test('closed state preserves exception details', function () {
    $circuitBreaker = new CircuitBreaker('test-service');
    $closedState = new ClosedState;

    $originalException = new RuntimeException('Original error', 123);

    $callable = function () use ($originalException) {
        throw $originalException;
    };

    try {
        $closedState->call($circuitBreaker, $callable);
        expect(false)->toBeTrue(); // Should not reach here
    } catch (RuntimeException $e) {
        expect($e)->toBe($originalException);
        expect($e->getMessage())->toBe('Original error');
        expect($e->getCode())->toBe(123);
    }
});

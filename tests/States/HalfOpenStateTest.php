<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\States\HalfOpenState;

// Test HalfOpenState functionality
test('half-open state getName returns correct state name', function () {
    $halfOpenState = new HalfOpenState;
    expect($halfOpenState->getName())->toBe('half-open');
});

test('half-open state call executes successfully and increments success count', function () {
    // Create a circuit breaker in half-open state
    $circuitBreaker = new CircuitBreaker('test-service', [
        'success_threshold' => 3,
    ]);

    // Force the circuit to half-open
    $circuitBreaker->halfOpen();

    expect($circuitBreaker->getSuccessCount())->toBe(0);

    // Create a HalfOpenState instance
    $halfOpenState = new HalfOpenState;

    // Call should succeed and increment success count
    $callable = function () {
        return 'success';
    };

    $result = $halfOpenState->call($circuitBreaker, $callable);

    expect($result)->toBe('success');
    expect($circuitBreaker->getSuccessCount())->toBe(1);
    expect($circuitBreaker->getState())->toBe('half-open'); // Still half-open
});

test('half-open state transitions to closed when success threshold reached', function () {
    // Create a circuit breaker with low success threshold
    $circuitBreaker = new CircuitBreaker('test-service', [
        'success_threshold' => 2,
    ]);

    // Force the circuit to half-open
    $circuitBreaker->halfOpen();

    // Increment success count to threshold - 1
    $circuitBreaker->incrementSuccessCount();
    expect($circuitBreaker->getSuccessCount())->toBe(1);
    expect($circuitBreaker->getState())->toBe('half-open');

    // Create a HalfOpenState instance
    $halfOpenState = new HalfOpenState;

    // This success should trigger the circuit to close
    $callable = function () {
        return 'final success';
    };

    $result = $halfOpenState->call($circuitBreaker, $callable);

    // Verify circuit is now closed
    expect($result)->toBe('final success');
    expect($circuitBreaker->getState())->toBe('closed');
    expect($circuitBreaker->getSuccessCount())->toBe(0); // Reset on close
});

test('half-open state handles failure and reopens circuit', function () {
    // Create a circuit breaker in half-open state
    $circuitBreaker = new CircuitBreaker('test-service', [
        'success_threshold' => 3,
    ]);

    // Force the circuit to half-open with some successes
    $circuitBreaker->halfOpen();
    $circuitBreaker->incrementSuccessCount();
    $circuitBreaker->incrementSuccessCount();
    expect($circuitBreaker->getSuccessCount())->toBe(2);

    // Create a HalfOpenState instance
    $halfOpenState = new HalfOpenState;

    // Callable that throws exception
    $callable = function () {
        throw new Exception('Test failure');
    };

    // Call should throw exception and reopen circuit
    try {
        $halfOpenState->call($circuitBreaker, $callable);
        expect(false)->toBeTrue(); // Should not reach here
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Test failure');
    }

    // Verify circuit is now open
    expect($circuitBreaker->getState())->toBe('open');
    expect($circuitBreaker->getSuccessCount())->toBe(0); // Reset on open
});

test('half-open state reportSuccess increments success count', function () {
    $halfOpenState = new HalfOpenState;

    // Create a circuit breaker
    $circuitBreaker = new CircuitBreaker('test-service', [
        'success_threshold' => 10,
    ]);

    // Force to half-open
    $circuitBreaker->halfOpen();

    expect($circuitBreaker->getSuccessCount())->toBe(0);

    // Call reportSuccess
    $halfOpenState->reportSuccess($circuitBreaker);

    expect($circuitBreaker->getSuccessCount())->toBe(1);
    expect($circuitBreaker->getState())->toBe('half-open');
});

test('half-open state reportSuccess closes circuit when threshold reached', function () {
    $halfOpenState = new HalfOpenState;

    // Create a circuit breaker with threshold of 2
    $circuitBreaker = new CircuitBreaker('test-service', [
        'success_threshold' => 2,
    ]);

    // Force to half-open
    $circuitBreaker->halfOpen();

    // First success
    $halfOpenState->reportSuccess($circuitBreaker);
    expect($circuitBreaker->getState())->toBe('half-open');

    // Second success should close
    $halfOpenState->reportSuccess($circuitBreaker);
    expect($circuitBreaker->getState())->toBe('closed');
});

test('half-open state reportFailure resets success count and opens circuit', function () {
    $halfOpenState = new HalfOpenState;

    // Create a circuit breaker
    $circuitBreaker = new CircuitBreaker('test-service', [
        'success_threshold' => 5,
    ]);

    // Force to half-open with some successes
    $circuitBreaker->halfOpen();
    $circuitBreaker->incrementSuccessCount();
    $circuitBreaker->incrementSuccessCount();
    $circuitBreaker->incrementSuccessCount();
    expect($circuitBreaker->getSuccessCount())->toBe(3);

    // Call reportFailure
    $halfOpenState->reportFailure($circuitBreaker);

    // Verify circuit is open and success count is reset
    expect($circuitBreaker->getState())->toBe('open');
    expect($circuitBreaker->getSuccessCount())->toBe(0);
});

test('half-open state handles multiple successful calls before threshold', function () {
    $circuitBreaker = new CircuitBreaker('test-service', [
        'success_threshold' => 5,
    ]);
    $circuitBreaker->halfOpen();

    $halfOpenState = new HalfOpenState;

    $callCount = 0;
    $callable = function () use (&$callCount) {
        $callCount++;

        return "success {$callCount}";
    };

    // Make 4 successful calls (one less than threshold)
    for ($i = 0; $i < 4; $i++) {
        $result = $halfOpenState->call($circuitBreaker, $callable);
        expect($result)->toBe('success '.($i + 1));
        expect($circuitBreaker->getState())->toBe('half-open');
    }

    expect($callCount)->toBe(4);
    expect($circuitBreaker->getSuccessCount())->toBe(4);

    // Fifth call should close the circuit
    $result = $halfOpenState->call($circuitBreaker, $callable);
    expect($result)->toBe('success 5');
    expect($circuitBreaker->getState())->toBe('closed');
});

test('half-open state preserves exception details on failure', function () {
    $circuitBreaker = new CircuitBreaker('test-service');
    $circuitBreaker->halfOpen();

    $halfOpenState = new HalfOpenState;

    $originalException = new RuntimeException('Half-open failure', 456);

    $callable = function () use ($originalException) {
        throw $originalException;
    };

    try {
        $halfOpenState->call($circuitBreaker, $callable);
        expect(false)->toBeTrue(); // Should not reach here
    } catch (RuntimeException $e) {
        expect($e)->toBe($originalException);
        expect($e->getMessage())->toBe('Half-open failure');
        expect($e->getCode())->toBe(456);
    }

    expect($circuitBreaker->getState())->toBe('open');
});

test('half-open state any failure immediately reopens circuit', function () {
    $circuitBreaker = new CircuitBreaker('test-service', [
        'success_threshold' => 10,
    ]);
    $circuitBreaker->halfOpen();

    // Build up success count
    for ($i = 0; $i < 9; $i++) {
        $circuitBreaker->incrementSuccessCount();
    }
    expect($circuitBreaker->getSuccessCount())->toBe(9);

    $halfOpenState = new HalfOpenState;

    // Even a single failure should reopen, regardless of success count
    try {
        $halfOpenState->call($circuitBreaker, function () {
            throw new Exception('Single failure');
        });
    } catch (Exception $e) {
        // Expected
    }

    expect($circuitBreaker->getState())->toBe('open');
    expect($circuitBreaker->getSuccessCount())->toBe(0);
});

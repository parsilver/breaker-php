<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Events\Events;
use Farzai\Breaker\Storage\InMemoryStorage;

// Test CircuitBreaker state transitions and event dispatching
test('circuit breaker dispatches state change events', function () {
    // Create a circuit breaker
    $breaker = new CircuitBreaker('test-service');

    // Track event dispatches
    $stateChangeEvents = [];
    $openEvents = [];
    $closeEvents = [];
    $halfOpenEvents = [];

    // Add listeners for state change events
    $breaker->onStateChange(function ($newState, $oldState, $circuitBreaker) use (&$stateChangeEvents) {
        $stateChangeEvents[] = ['new' => $newState, 'old' => $oldState];
    });

    $breaker->onOpen(function ($circuitBreaker) use (&$openEvents) {
        $openEvents[] = true;
    });

    $breaker->onClose(function ($circuitBreaker) use (&$closeEvents) {
        $closeEvents[] = true;
    });

    $breaker->onHalfOpen(function ($circuitBreaker) use (&$halfOpenEvents) {
        $halfOpenEvents[] = true;
    });

    // Initially in closed state, so no events yet
    expect($stateChangeEvents)->toBeEmpty();
    expect($openEvents)->toBeEmpty();
    expect($closeEvents)->toBeEmpty();
    expect($halfOpenEvents)->toBeEmpty();

    // Transition to open state
    $breaker->open();

    // Verify state change events
    expect($stateChangeEvents)->toHaveCount(1);
    expect($stateChangeEvents[0]['new'])->toBe('open');
    expect($stateChangeEvents[0]['old'])->toBe('closed');
    expect($openEvents)->toHaveCount(1);

    // Transition to half-open state
    $breaker->halfOpen();

    // Verify state change events
    expect($stateChangeEvents)->toHaveCount(2);
    expect($stateChangeEvents[1]['new'])->toBe('half-open');
    expect($stateChangeEvents[1]['old'])->toBe('open');
    expect($halfOpenEvents)->toHaveCount(1);

    // Transition to closed state
    $breaker->close();

    // Verify state change events
    expect($stateChangeEvents)->toHaveCount(3);
    expect($stateChangeEvents[2]['new'])->toBe('closed');
    expect($stateChangeEvents[2]['old'])->toBe('half-open');
    expect($closeEvents)->toHaveCount(1);
});

test('circuit breaker initializes state from storage', function () {
    // Create a shared storage instance
    $storage = new InMemoryStorage;

    // Create a circuit breaker with custom options
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 3,
        'success_threshold' => 2,
        'timeout' => 30,
    ], $storage);

    // Force the circuit to open
    $breaker->open();

    // Increment failure count
    $breaker->incrementFailureCount();
    $breaker->incrementFailureCount();

    // Create a new circuit breaker with the same service key and storage
    $newBreaker = new CircuitBreaker('test-service', [], $storage);

    // Verify the state was loaded correctly
    expect($newBreaker->getState())->toBe('open');
    expect($newBreaker->getFailureCount())->toBe(2);
});

test('circuit breaker saves state to storage', function () {
    // Create a shared storage instance
    $storage = new InMemoryStorage;

    // Create a unique service key for this test
    $serviceKey = 'test-service-'.uniqid();

    // Create a circuit breaker
    $breaker = new CircuitBreaker($serviceKey, [], $storage);

    // Initially in closed state
    expect($breaker->getState())->toBe('closed');

    // Force the circuit to open
    $breaker->open();

    // Create a new circuit breaker with the same service key and storage
    $newBreaker = new CircuitBreaker($serviceKey, [], $storage);

    // Verify the state was loaded correctly
    expect($newBreaker->getState())->toBe('open');
});

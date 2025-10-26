<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\DefaultCircuitStateRepository;
use Farzai\Breaker\Storage\JsonStorageSerializer;

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
    $breaker->onStateChange(function ($event) use (&$stateChangeEvents) {
        $stateChangeEvents[] = ['new' => $event->getNewState(), 'old' => $event->getPreviousState()];
    });

    $breaker->onOpen(function ($event) use (&$openEvents) {
        $openEvents[] = true;
    });

    $breaker->onClose(function ($event) use (&$closeEvents) {
        $closeEvents[] = true;
    });

    $breaker->onHalfOpen(function ($event) use (&$halfOpenEvents) {
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
    // Create a shared repository instance
    $adapter = new InMemoryStorageAdapter;
    $repository = new DefaultCircuitStateRepository($adapter, new JsonStorageSerializer);

    // Create a circuit breaker with custom options
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 3,
        'timeout' => 5,
        'success_threshold' => 2,
    ], $repository);

    // Force the circuit to open
    $breaker->open();

    // Increment failure count
    $breaker->incrementFailureCount();
    $breaker->incrementFailureCount();

    // Create a new circuit breaker with the same repository
    $breaker2 = new CircuitBreaker('test-service', [
        'failure_threshold' => 3,
        'timeout' => 5,
        'success_threshold' => 2,
    ], $repository);

    // Verify the state was loaded correctly
    expect($breaker2->getState())->toBe('open');
    expect($breaker2->getFailureCount())->toBe(2);
});

test('circuit breaker saves state to storage', function () {
    // Create a shared repository instance with a unique service key
    $adapter = new InMemoryStorageAdapter;
    $repository = new DefaultCircuitStateRepository($adapter, new JsonStorageSerializer);
    $serviceKey = 'test-service-'.uniqid();

    // Create a circuit breaker
    $breaker = new CircuitBreaker($serviceKey, [
        'failure_threshold' => 2,
        'timeout' => 5,
        'success_threshold' => 2,
    ], $repository);

    // Initially in closed state
    expect($breaker->getState())->toBe('closed');

    // Force the circuit to open and increment failure count
    $breaker->open();
    $breaker->incrementFailureCount();
    $breaker->incrementFailureCount();

    // Verify storage content directly
    $storedState = $repository->find($serviceKey);
    expect($storedState)->not->toBeNull();
    expect($storedState->state)->toBe('open');
    expect($storedState->failureCount)->toBe(2);
});

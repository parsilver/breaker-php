<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Storage\InMemoryStorage;

// Test CircuitBreaker initial state handling
test('circuit breaker does not dispatch events on initial state', function () {
    // Create a storage with pre-defined state
    $storage = new InMemoryStorage;
    $serviceKey = 'test-initial-state';

    // Set up event tracking
    $stateChangeEventTriggered = false;
    $openEventTriggered = false;

    // Create a circuit breaker
    $breaker = new CircuitBreaker($serviceKey, [], $storage);

    // Add event listeners
    $breaker->onStateChange(function () use (&$stateChangeEventTriggered) {
        $stateChangeEventTriggered = true;
    });

    $breaker->onOpen(function () use (&$openEventTriggered) {
        $openEventTriggered = true;
    });

    // Verify no events were triggered during initialization
    expect($stateChangeEventTriggered)->toBeFalse();
    expect($openEventTriggered)->toBeFalse();
});

test('circuit breaker handles invalid state data from storage', function () {
    // Create a storage with invalid state data
    $storage = new InMemoryStorage;
    $serviceKey = 'test-invalid-state';

    // Save invalid state data (missing required fields)
    $storage->save($serviceKey, [
        'state' => 'invalid-state',
        // Missing other required fields
    ]);

    // Create a circuit breaker with the invalid state data
    $breaker = new CircuitBreaker($serviceKey, [], $storage);

    // Verify the circuit breaker defaults to closed state
    expect($breaker->getState())->toBe('closed');
});

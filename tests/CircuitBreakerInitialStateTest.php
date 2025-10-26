<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\DefaultCircuitStateRepository;
use Farzai\Breaker\Storage\JsonStorageSerializer;

// Test CircuitBreaker initial state handling
test('circuit breaker does not dispatch events on initial state', function () {
    // Create a repository with pre-defined state
    $adapter = new InMemoryStorageAdapter;
    $repository = new DefaultCircuitStateRepository($adapter, new JsonStorageSerializer);
    $serviceKey = 'test-initial-state';

    // Set up event tracking
    $stateChangeEventTriggered = false;
    $openEventTriggered = false;

    // Create a circuit breaker instance which will load the initial state
    $breaker = new CircuitBreaker($serviceKey, [
        'failure_threshold' => 5,
        'timeout' => 30,
        'success_threshold' => 2,
    ], $repository);

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
    // Create a repository with invalid state data
    $adapter = new InMemoryStorageAdapter;
    $repository = new DefaultCircuitStateRepository($adapter, new JsonStorageSerializer);
    $serviceKey = 'test-invalid-state';

    // Save invalid state data using adapter directly
    $key = 'cb_'.hash('sha256', $serviceKey);
    $adapter->write($key, '{"state":"invalid-state"}');  // Missing required fields

    // Create a circuit breaker with the invalid data
    $breaker = new CircuitBreaker($serviceKey, [
        'failure_threshold' => 5,
        'timeout' => 30,
        'success_threshold' => 2,
    ], $repository);

    // Verify the circuit breaker defaults to closed state
    expect($breaker->getState())->toBe('closed');
});

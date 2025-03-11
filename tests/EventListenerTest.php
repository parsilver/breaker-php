<?php

use Farzai\Breaker\CircuitBreaker;

// Test state transition events
test('event listeners are triggered on state transitions', function () {
    $events = [];

    // Create a circuit breaker with a failure threshold of 1
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
        'timeout' => 0, // No waiting time for this test
    ]);

    // Add listeners for all state transitions
    $breaker->onStateChange(function ($newState, $oldState, $breaker) use (&$events) {
        $events[] = [
            'event' => 'state_change',
            'old_state' => $oldState,
            'new_state' => $newState,
            'service' => $breaker->getServiceKey(),
        ];
    });

    $breaker->onOpen(function ($breaker) use (&$events) {
        $events[] = [
            'event' => 'open',
            'service' => $breaker->getServiceKey(),
        ];
    });

    $breaker->onClose(function ($breaker) use (&$events) {
        $events[] = [
            'event' => 'close',
            'service' => $breaker->getServiceKey(),
        ];
    });

    $breaker->onHalfOpen(function ($breaker) use (&$events) {
        $events[] = [
            'event' => 'half_open',
            'service' => $breaker->getServiceKey(),
        ];
    });

    // Initially the circuit is closed
    expect($breaker->getState())->toBe('closed');
    expect($events)->toHaveCount(0);

    // Trip the breaker to open state
    try {
        $breaker->call(function () {
            throw new \Exception('Service error');
        });
    } catch (\Exception $e) {
        // Expected exception
    }

    // Check that open event was triggered
    expect($breaker->getState())->toBe('open');
    expect($events)->toHaveCount(2);
    expect($events[0]['event'])->toBe('state_change');
    expect($events[0]['old_state'])->toBe('closed');
    expect($events[0]['new_state'])->toBe('open');
    expect($events[1]['event'])->toBe('open');

    // Transition to half-open
    $breaker->call(function () {
        return 'success';
    });

    // Check that half-open event was triggered
    expect($breaker->getState())->toBe('half-open');
    expect($events)->toHaveCount(4);
    expect($events[2]['event'])->toBe('state_change');
    expect($events[2]['old_state'])->toBe('open');
    expect($events[2]['new_state'])->toBe('half-open');
    expect($events[3]['event'])->toBe('half_open');

    // Transition to closed
    $breaker->call(function () {
        return 'success';
    });

    // Check that close event was triggered
    expect($breaker->getState())->toBe('closed');
    expect($events)->toHaveCount(6);
    expect($events[4]['event'])->toBe('state_change');
    expect($events[4]['old_state'])->toBe('half-open');
    expect($events[4]['new_state'])->toBe('closed');
    expect($events[5]['event'])->toBe('close');
});

// Test success and failure events
test('event listeners are triggered on success and failure', function () {
    $events = [];

    // Create a circuit breaker
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 3,
    ]);

    // Add listeners for success and failure
    $breaker->onSuccess(function ($result, $breaker) use (&$events) {
        $events[] = [
            'event' => 'success',
            'result' => $result,
            'service' => $breaker->getServiceKey(),
        ];
    });

    $breaker->onFailure(function ($exception, $breaker) use (&$events) {
        $events[] = [
            'event' => 'failure',
            'error' => $exception->getMessage(),
            'service' => $breaker->getServiceKey(),
        ];
    });

    // Call the service successfully
    $result = $breaker->call(function () {
        return 'success-result';
    });

    // Check success event
    expect($events)->toHaveCount(1);
    expect($events[0]['event'])->toBe('success');
    expect($events[0]['result'])->toBe('success-result');

    // Call the service with failure
    try {
        $breaker->call(function () {
            throw new \Exception('Failure-error');
        });
    } catch (\Exception $e) {
        // Expected exception
    }

    // Check failure event
    expect($events)->toHaveCount(2);
    expect($events[1]['event'])->toBe('failure');
    expect($events[1]['error'])->toBe('Failure-error');
});

// Test event listeners with fallback
test('event listeners work with fallback mechanism', function () {
    $events = [];

    // Create a circuit breaker with a failure threshold of 1
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
    ]);

    // Add listeners
    $breaker->onFailure(function ($exception, $breaker) use (&$events) {
        $events[] = [
            'event' => 'failure',
            'error' => $exception->getMessage(),
        ];
    });

    $breaker->onFallbackSuccess(function ($result, $exception, $breaker) use (&$events) {
        $events[] = [
            'event' => 'fallback_success',
            'result' => $result,
            'error' => $exception->getMessage(),
        ];
    });

    // Use fallback on failure
    $result = $breaker->callWithFallback(
        function () {
            throw new \Exception('Service error');
        },
        function ($exception) {
            return 'fallback-result';
        }
    );

    // Check events
    expect($events)->toHaveCount(2);
    expect($events[0]['event'])->toBe('failure');
    expect($events[1]['event'])->toBe('fallback_success');
    expect($events[1]['result'])->toBe('fallback-result');

    // Trip the circuit
    try {
        $breaker->call(function () {
            throw new \Exception('Another error');
        });
    } catch (\Exception $e) {
        // Expected
    }

    // Remove failure listener
    $breaker->removeListener(1);

    // Reset events array
    $events = [];

    // Use fallback with open circuit
    $result = $breaker->callWithFallback(
        function () {
            return 'success'; // Won't execute
        },
        function ($exception) {
            return 'circuit-open-fallback';
        }
    );

    // Check events for fallback with open circuit
    expect($events)->toHaveCount(1);
    expect($events[0]['event'])->toBe('fallback_success');
    expect($events[0]['result'])->toBe('circuit-open-fallback');
});

// Test multiple listeners per event
test('multiple listeners can be added for the same event', function () {
    $counts = [
        'listener1' => 0,
        'listener2' => 0,
    ];

    // Create a circuit breaker
    $breaker = new CircuitBreaker('test-service');

    // Add multiple listeners for the same event
    $breaker->onSuccess(function () use (&$counts) {
        $counts['listener1']++;
    });

    $breaker->onSuccess(function () use (&$counts) {
        $counts['listener2']++;
    });

    // Make a successful call
    $breaker->call(function () {
        return 'success';
    });

    // Both listeners should have been called
    expect($counts['listener1'])->toBe(1);
    expect($counts['listener2'])->toBe(1);
});

// Test event listener removal
test('event listeners can be removed', function () {
    $counter = 0;

    // Create a circuit breaker
    $breaker = new CircuitBreaker('test-service');

    // Add a listener
    $listenerId = $breaker->onSuccess(function () use (&$counter) {
        $counter++;
    });

    // Make a successful call
    $breaker->call(function () {
        return 'success';
    });

    // Counter should be incremented
    expect($counter)->toBe(1);

    // Remove the listener
    $breaker->removeListener($listenerId);

    // Make another successful call
    $breaker->call(function () {
        return 'success again';
    });

    // Counter should not have changed
    expect($counter)->toBe(1);
});

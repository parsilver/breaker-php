<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;
use Farzai\Breaker\States\OpenState;

// Test OpenState functionality
test('open state getName returns correct state name', function () {
    $openState = new OpenState;
    expect($openState->getName())->toBe('open');
});

test('open state reportSuccess does nothing', function () {
    $openState = new OpenState;

    // Create a circuit breaker
    $circuitBreaker = new CircuitBreaker('test-service');

    // Force the circuit breaker to open
    $circuitBreaker->open();

    // Call reportSuccess
    $openState->reportSuccess($circuitBreaker);

    // Verify the state is still open
    expect($circuitBreaker->getState())->toBe('open');
});

test('open state reportFailure does nothing', function () {
    $openState = new OpenState;

    // Create a circuit breaker
    $circuitBreaker = new CircuitBreaker('test-service');

    // Force the circuit breaker to open
    $circuitBreaker->open();

    // Call reportFailure
    $openState->reportFailure($circuitBreaker);

    // Verify the state is still open
    expect($circuitBreaker->getState())->toBe('open');
});

test('open state call throws exception when timeout not reached', function () {
    // Create a circuit breaker with a long timeout
    $circuitBreaker = new CircuitBreaker('test-service', [
        'timeout' => 60, // 60 seconds
    ]);

    // Force the circuit breaker to open
    $circuitBreaker->open();

    // Create an OpenState instance
    $openState = new OpenState;

    // The callable should not be called
    $callable = function () {
        return 'This should not be called';
    };

    // Call should throw CircuitOpenException
    expect(fn () => $openState->call($circuitBreaker, $callable))
        ->toThrow(CircuitOpenException::class);
});

test('open state call transitions to half-open when timeout reached', function () {
    // Create a circuit breaker with a very short timeout
    $circuitBreaker = new CircuitBreaker('test-service', [
        'timeout' => 0, // Immediate timeout
    ]);

    // Force the circuit breaker to open
    $circuitBreaker->open();

    // Set the last failure time to the past to ensure timeout is reached
    $reflection = new ReflectionClass($circuitBreaker);
    $property = $reflection->getProperty('lastFailureTime');
    $property->setAccessible(true);
    $property->setValue($circuitBreaker, time() - 10);

    // Create an OpenState instance
    $openState = new OpenState;

    // The callable that should be called when the circuit transitions to half-open
    $callableCalled = false;
    $callable = function () use (&$callableCalled) {
        $callableCalled = true;

        return 'success';
    };

    // Call should not throw an exception
    $result = $openState->call($circuitBreaker, $callable);

    // Verify the circuit is now half-open
    expect($circuitBreaker->getState())->toBe('half-open');

    // Verify the callable was called
    expect($callableCalled)->toBeTrue();

    // Verify the result
    expect($result)->toBe('success');
});

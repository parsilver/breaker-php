<?php

require_once __DIR__.'/../vendor/autoload.php';

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;

// Create a circuit breaker instance
$breaker = new CircuitBreaker('api-service', [
    'failure_threshold' => 2,    // Number of failures before opening the circuit
    'success_threshold' => 1,    // Number of successes in half-open state before closing
    'timeout' => 2,              // Seconds to wait before trying again (half-open)
]);

// Add event listeners
echo "Adding event listeners...\n";

// Listen to state transitions
$breaker->onStateChange(function ($newState, $oldState, $breaker) {
    echo "Circuit state changed from {$oldState} to {$newState} for service {$breaker->getServiceKey()}\n";
});

// Listen to specific state transitions
$breaker->onOpen(function ($breaker) {
    echo "Circuit opened! Service {$breaker->getServiceKey()} is experiencing problems.\n";
    echo "Will try again after {$breaker->getTimeout()} seconds.\n";

    // In a real application, you might:
    // - Send a notification to an admin
    // - Log the event to a monitoring system
    // - Update a dashboard status
});

$breaker->onHalfOpen(function ($breaker) {
    echo "Circuit half-open! Testing if service {$breaker->getServiceKey()} is back online...\n";
});

$breaker->onClose(function ($breaker) {
    echo "Circuit closed! Service {$breaker->getServiceKey()} is back to normal operation.\n";
});

// Listen to call success and failures
$breaker->onSuccess(function ($result, $breaker) {
    echo 'Successful call! Result: '.json_encode($result)."\n";
});

$breaker->onFailure(function ($exception, $breaker) {
    echo "Call failed! Error: {$exception->getMessage()}\n";
    echo "Failure count: {$breaker->getFailureCount()}/{$breaker->getFailureThreshold()}\n";
});

// Listen to fallback usage
$breaker->onFallbackSuccess(function ($result, $exception, $breaker) {
    echo 'Fallback used! Result: '.json_encode($result)."\n";
    echo "Original error: {$exception->getMessage()}\n";
});

// Simulate API calls
echo "\nExample 1: Successful call\n";
$breaker->call(function () {
    return ['status' => 'success', 'data' => 'example data'];
});

echo "\nExample 2: Failing calls until circuit opens\n";
try {
    // First failure
    $breaker->call(function () {
        throw new \Exception('Service error 1');
    });
} catch (\Exception $e) {
    // Expected exception, handled by onFailure listener
}

try {
    // Second failure - should open the circuit
    $breaker->call(function () {
        throw new \Exception('Service error 2');
    });
} catch (\Exception $e) {
    // Expected exception, handled by onFailure listener
}

echo "\nExample 3: Calls with open circuit\n";
try {
    // Circuit is open, should fail fast
    $breaker->call(function () {
        return 'This will not execute';
    });
} catch (CircuitOpenException $e) {
    echo "Circuit open exception: {$e->getMessage()}\n";
}

// Use fallback with open circuit
echo "\nExample 4: Using fallback with open circuit\n";
$result = $breaker->callWithFallback(
    function () {
        return 'This will not execute because circuit is open';
    },
    function ($exception) {
        return ['status' => 'fallback', 'data' => 'cached data'];
    }
);

echo "\nExample 5: Circuit recovery\n";
echo "Waiting for circuit timeout ({$breaker->getTimeout()} seconds)...\n";
sleep($breaker->getTimeout());

// After timeout, should transition to half-open
$breaker->callWithFallback(
    function () {
        return ['status' => 'success', 'data' => 'service back online'];
    },
    function ($exception) {
        return ['status' => 'fallback', 'data' => 'cached data'];
    }
);

echo "\nFinal circuit state: ".$breaker->getState()."\n";

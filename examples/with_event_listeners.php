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

// Listen to state transitions with new event objects
$breaker->onStateChange(function ($event) {
    echo sprintf(
        "Circuit state changed from %s to %s for service %s\n",
        $event->getPreviousState(),
        $event->getNewState(),
        $event->getServiceKey()
    );
});

// Listen to specific state transitions with typed events
$breaker->onOpen(function ($event) {
    echo "Circuit opened! Service {$event->getServiceKey()} is experiencing problems.\n";
    echo "Failure count: {$event->getFailureCount()}/{$event->getFailureThreshold()}\n";
    echo "Will try again after {$event->getTimeout()} seconds.\n";
    echo 'Half-open at: '.date('H:i:s', $event->getHalfOpenTimestamp())."\n";

    // In a real application, you might:
    // - Send a notification to an admin
    // - Log the event to a monitoring system
    // - Update a dashboard status
    // - Trigger alerting based on event properties
});

$breaker->onHalfOpen(function ($event) {
    echo "Circuit half-open! Testing if service {$event->getServiceKey()} is back online...\n";
    echo "Need {$event->getSuccessThreshold()} successful calls to close.\n";
});

$breaker->onClose(function ($event) {
    $message = $event->isRecovery()
        ? 'recovered from failure'
        : 'reset';
    echo "Circuit closed! Service {$event->getServiceKey()} has {$message}.\n";
});

// Listen to call success and failures with detailed metrics
$breaker->onSuccess(function ($event) {
    echo 'Successful call! Result: '.json_encode($event->getResult())."\n";
    echo sprintf("Execution time: %.2fms\n", $event->getExecutionTime());
    echo "Current state: {$event->getCurrentState()}\n";
});

$breaker->onFailure(function ($event) {
    echo "Call failed! Error: {$event->getExceptionMessage()}\n";
    echo "Exception type: {$event->getExceptionClass()}\n";
    echo sprintf("Execution time before failure: %.2fms\n", $event->getExecutionTime());
    echo "Failure count: {$event->getFailureCount()}/{$event->getFailureThreshold()}\n";

    if ($event->willTriggerOpen()) {
        echo "⚠️  This failure will trigger the circuit to open!\n";
    }
});

// Listen to fallback usage with comprehensive info
$breaker->onFallbackSuccess(function ($event) {
    echo 'Fallback used! Result: '.json_encode($event->getResult())."\n";
    echo "Original error: {$event->getOriginalExceptionMessage()}\n";
    echo sprintf("Fallback execution time: %.2fms\n", $event->getExecutionTime());
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

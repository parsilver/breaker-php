<?php

require_once __DIR__.'/../vendor/autoload.php';

use Farzai\Breaker\Breaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;

/**
 * Basic Usage Example - Using the Breaker Facade
 *
 * This example demonstrates the easiest way to get started
 * with circuit breakers using the Breaker facade.
 */
echo "=== Basic Circuit Breaker Usage ===\n\n";

// Simulate a service call
function callExternalService($shouldFail = false)
{
    if ($shouldFail) {
        throw new Exception('Service is unavailable');
    }

    return 'Service response data';
}

// Example 1: Simple successful call
echo "Example 1: Simple successful call\n";
echo "-----------------------------------\n";

$result = Breaker::protect('api-service', function () {
    return callExternalService(false);
});

echo "Success: {$result}\n";
echo 'Current state: '.Breaker::instance('api-service')->getState()."\n\n";

// Example 2: With automatic fallback
echo "Example 2: With automatic fallback\n";
echo "-----------------------------------\n";

$result = Breaker::protect(
    'api-service',
    function () {
        return callExternalService(true); // This will fail
    },
    fallback: function ($exception) {
        echo "Fallback executed: {$exception->getMessage()}\n";

        return 'Cached backup data';
    }
);

echo "Result: {$result}\n\n";

// Example 3: Triggering the circuit breaker
echo "Example 3: Triggering circuit open\n";
echo "------------------------------------\n";

// Clear previous state
Breaker::forget('failing-service');

// Make several failing calls to trigger the circuit breaker
for ($i = 1; $i <= 7; $i++) {
    try {
        $result = Breaker::protect('failing-service', function () {
            return callExternalService(true); // This will fail
        });
    } catch (CircuitOpenException $e) {
        echo "Attempt #{$i}: Circuit is OPEN - failing fast!\n";
        break;
    } catch (Exception $e) {
        echo "Attempt #{$i}: Service error - {$e->getMessage()}\n";
    }
}

$breaker = Breaker::instance('failing-service');
echo "Final state: {$breaker->getState()}\n";
echo "Failures: {$breaker->getFailureCount()}/{$breaker->getFailureThreshold()}\n\n";

// Example 4: Circuit open with fallback
echo "Example 4: Circuit open with fallback\n";
echo "--------------------------------------\n";

$result = Breaker::protect(
    'failing-service',
    function () {
        return 'This will not execute - circuit is open';
    },
    fallback: function ($exception, $circuitBreaker) {
        echo "Circuit state: {$circuitBreaker->getState()}\n";

        return 'Using cached data as fallback';
    }
);

echo "Result: {$result}\n\n";

// Example 5: Health monitoring
echo "Example 5: Health monitoring\n";
echo "-----------------------------\n";

$health = Breaker::healthReport();
foreach ($health as $service => $report) {
    echo "{$service}:\n";
    echo "  Status: {$report->status->value}\n";
    echo "  State: {$report->state}\n";
    echo "  Failures: {$report->failureCount}/{$report->failureThreshold}\n";
    echo "  Message: {$report->message}\n\n";
}

echo "=== Summary ===\n";
echo "✅ The Breaker facade provides the easiest way to use circuit breakers\n";
echo "✅ Just call Breaker::protect() with your service name and callable\n";
echo "✅ Add a fallback parameter for automatic fallback behavior\n";
echo "✅ Circuit breakers are automatically created and managed for you\n";

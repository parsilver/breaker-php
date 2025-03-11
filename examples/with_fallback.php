<?php

require_once __DIR__.'/../vendor/autoload.php';

use Farzai\Breaker\CircuitBreaker;

// Create a circuit breaker for a specific service
$breaker = new CircuitBreaker('api-service', [
    'failure_threshold' => 3,    // Number of failures before opening the circuit
    'success_threshold' => 2,    // Number of successes in half-open state before closing
    'timeout' => 10,             // Seconds to wait before trying again (half-open)
]);

// Simulate an API call
function callAPI($shouldFail = false)
{
    if ($shouldFail) {
        throw new Exception('API call failed');
    }

    return ['status' => 'success', 'data' => ['id' => 1, 'name' => 'John Doe']];
}

// Example 1: Using fallback with a successful call
echo "Example 1: Successful call with fallback\n";
$result = $breaker->callWithFallback(
    function () {
        return callAPI(false); // Successful call
    },
    function ($exception) {
        echo 'Fallback executed: '.$exception->getMessage()."\n";

        return ['status' => 'error', 'data' => ['error' => 'Fallback response']];
    }
);
echo 'Result: '.json_encode($result)."\n";
echo 'Circuit state: '.$breaker->getState()."\n\n";

// Example 2: Using fallback with a failing call
echo "Example 2: Failing call with fallback\n";
$result = $breaker->callWithFallback(
    function () {
        return callAPI(true); // Failing call
    },
    function ($exception) {
        echo 'Fallback executed: '.$exception->getMessage()."\n";

        return ['status' => 'error', 'data' => ['error' => 'Fallback response']];
    }
);
echo 'Result: '.json_encode($result)."\n";
echo 'Circuit state: '.$breaker->getState()."\n";
echo 'Failure count: '.$breaker->getFailureCount()."\n\n";

// Example 3: Trip the circuit breaker
echo "Example 3: Tripping the circuit\n";
try {
    // Make enough failures to trip the circuit
    for ($i = 0; $i < 3; $i++) {
        try {
            $breaker->call(function () {
                return callAPI(true); // Failing call
            });
        } catch (Exception $e) {
            echo 'Call failed: '.$e->getMessage()."\n";
        }
    }
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
echo 'Circuit state: '.$breaker->getState()."\n\n";

// Example 4: Using fallback with open circuit
echo "Example 4: Call with fallback when circuit is open\n";
$result = $breaker->callWithFallback(
    function () {
        return callAPI(false); // Would be successful, but circuit is open
    },
    function ($exception, $breaker) {
        echo 'Fallback executed: '.$exception->getMessage()."\n";
        echo 'Fallback received circuit breaker state: '.$breaker->getState()."\n";

        return ['status' => 'error', 'data' => ['error' => 'Service unavailable - using cached data']];
    }
);
echo 'Result: '.json_encode($result)."\n";
echo 'Circuit state: '.$breaker->getState()."\n\n";

// Example 5: Demonstrate how the circuit automatically recovers
echo "Example 5: Recovery after timeout\n";
echo "Waiting for circuit timeout...\n";

// Force circuit to half-open for demo purposes
$breaker->halfOpen();
echo 'Circuit state after timeout: '.$breaker->getState()."\n";

// Successful calls in half-open state
$successfulCalls = 0;
while ($breaker->getState() !== 'closed') {
    $result = $breaker->callWithFallback(
        function () {
            return callAPI(false); // Successful call to recover
        },
        function ($exception) {
            return ['status' => 'error', 'data' => ['error' => 'Fallback response']];
        }
    );
    $successfulCalls++;
    echo 'Success count: '.$breaker->getSuccessCount()."\n";
}

echo "Circuit closed after $successfulCalls successful calls\n";
echo 'Final circuit state: '.$breaker->getState()."\n";

<?php

require_once __DIR__.'/../vendor/autoload.php';

use Farzai\Breaker\Breaker;

/**
 * Fallback Pattern Examples - Using the Breaker Facade
 *
 * This example demonstrates various fallback strategies
 * for handling failures gracefully.
 */
echo "=== Circuit Breaker with Fallback Examples ===\n\n";

// Simulate an API call
function callAPI($shouldFail = false)
{
    if ($shouldFail) {
        throw new Exception('API call failed');
    }

    return ['status' => 'success', 'data' => ['id' => 1, 'name' => 'John Doe']];
}

// Pre-configure services
Breaker::configure('api-service', [
    'failure_threshold' => 3,
    'success_threshold' => 2,
    'timeout' => 10,
]);

// Example 1: Using fallback with a successful call
echo "Example 1: Successful call with fallback\n";
echo "-----------------------------------------\n";

$result = Breaker::protect(
    'api-service',
    fn () => callAPI(false),
    fallback: function ($exception) {
        echo "Fallback executed: {$exception->getMessage()}\n";

        return ['status' => 'error', 'data' => ['error' => 'Fallback response']];
    }
);

echo 'Result: '.json_encode($result)."\n";
echo 'Circuit state: '.Breaker::instance('api-service')->getState()."\n\n";

// Example 2: Using fallback with a failing call
echo "Example 2: Failing call with fallback\n";
echo "--------------------------------------\n";

$result = Breaker::protect(
    'api-service',
    fn () => callAPI(true),
    fallback: function ($exception) {
        echo "Fallback executed: {$exception->getMessage()}\n";

        return ['status' => 'error', 'data' => ['error' => 'Fallback response']];
    }
);

echo 'Result: '.json_encode($result)."\n";
echo 'Circuit state: '.Breaker::instance('api-service')->getState()."\n";
echo 'Failure count: '.Breaker::instance('api-service')->getFailureCount()."\n\n";

// Example 3: Trip the circuit breaker
echo "Example 3: Tripping the circuit\n";
echo "--------------------------------\n";

// Make enough failures to trip the circuit
for ($i = 0; $i < 3; $i++) {
    try {
        Breaker::protect('api-service', fn () => callAPI(true));
    } catch (Exception $e) {
        echo "Call failed: {$e->getMessage()}\n";
    }
}

echo 'Circuit state: '.Breaker::instance('api-service')->getState()."\n\n";

// Example 4: Using fallback with open circuit
echo "Example 4: Call with fallback when circuit is open\n";
echo "---------------------------------------------------\n";

$result = Breaker::protect(
    'api-service',
    fn () => callAPI(false), // Would succeed, but circuit is open
    fallback: function ($exception, $breaker) {
        echo "Fallback executed: {$exception->getMessage()}\n";
        echo "Circuit state: {$breaker->getState()}\n";

        return ['status' => 'cached', 'data' => ['message' => 'Using cached data']];
    }
);

echo 'Result: '.json_encode($result)."\n\n";

// Example 5: Demonstrate how the circuit automatically recovers
echo "Example 5: Recovery after timeout\n";
echo "----------------------------------\n";

// Force circuit to half-open for demo purposes
Breaker::instance('api-service')->halfOpen();
echo 'Circuit state after timeout: '.Breaker::instance('api-service')->getState()."\n";

// Successful calls in half-open state
$successfulCalls = 0;
$breaker = Breaker::instance('api-service');

while ($breaker->getState() !== 'closed') {
    $result = Breaker::protect(
        'api-service',
        fn () => callAPI(false),
        fallback: fn ($e) => ['status' => 'error']
    );
    $successfulCalls++;
    echo "Success count: {$breaker->getSuccessCount()}\n";
}

echo "Circuit closed after {$successfulCalls} successful calls\n";
echo "Final circuit state: {$breaker->getState()}\n\n";

// Example 6: Different fallback strategies
echo "Example 6: Different fallback strategies\n";
echo "-----------------------------------------\n";

Breaker::forget('strategy-demo');
Breaker::configure('strategy-demo', ['failure_threshold' => 1]);

// Strategy 1: Return cached data
$result = Breaker::protect(
    'strategy-demo',
    fn () => throw new Exception('Service down'),
    fallback: fn ($e) => ['cached' => true, 'data' => 'stale data']
);
echo 'Strategy 1 (Cache): '.json_encode($result)."\n";

// Strategy 2: Return default/empty value
$result = Breaker::protect(
    'strategy-demo',
    fn () => throw new Exception('Service down'),
    fallback: fn ($e) => []
);
echo 'Strategy 2 (Empty): '.json_encode($result)."\n";

// Strategy 3: Use alternative service
$result = Breaker::protect(
    'strategy-demo',
    fn () => throw new Exception('Primary service down'),
    fallback: function ($e) {
        echo "Switching to backup service...\n";

        return ['backup' => true, 'data' => 'from backup'];
    }
);
echo 'Strategy 3 (Backup): '.json_encode($result)."\n\n";

echo "=== Summary ===\n";
echo "✅ Fallbacks provide graceful degradation\n";
echo "✅ Use Breaker::protect() with fallback parameter\n";
echo "✅ Fallback receives exception and circuit breaker instance\n";
echo "✅ Choose appropriate fallback strategy for your use case\n";

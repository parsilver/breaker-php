<?php

require_once __DIR__.'/../vendor/autoload.php';

use Farzai\Breaker\Breaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;

/**
 * Facade Pattern Usage Examples
 *
 * This example demonstrates how to use the Breaker facade for a clean,
 * Laravel-inspired API that dramatically simplifies circuit breaker usage.
 */
echo "=== Breaker Facade Pattern Examples ===\n\n";

// Example 1: Simple one-liner protection
echo "Example 1: Simple one-liner protection\n";
echo "---------------------------------------\n";

$result = Breaker::protect('api-service', function () {
    echo "Calling API service...\n";

    return ['status' => 'success', 'data' => ['id' => 1, 'name' => 'John']];
});

echo 'Result: '.json_encode($result)."\n";
echo 'Service state: '.Breaker::instance('api-service')->getState()."\n\n";

// Example 2: Using with fallback
echo "Example 2: With automatic fallback\n";
echo "-----------------------------------\n";

$result = Breaker::protect(
    'payment-service',
    function () {
        // Simulate a failure
        throw new Exception('Payment gateway timeout');
    },
    fallback: function ($exception) {
        echo "Fallback executed due to: {$exception->getMessage()}\n";

        return ['status' => 'queued', 'message' => 'Payment queued for retry'];
    }
);

echo 'Result: '.json_encode($result)."\n\n";

// Example 3: With custom configuration
echo "Example 3: Custom configuration per call\n";
echo "-----------------------------------------\n";

$result = Breaker::protect(
    'email-service',
    function () {
        echo "Sending email...\n";

        return true;
    },
    config: [
        'failure_threshold' => 3,
        'timeout' => 60,
        'success_threshold' => 1,
    ]
);

echo 'Email sent: '.($result ? 'Yes' : 'No')."\n";
echo 'Configuration: failure_threshold='.Breaker::instance('email-service')->getFailureThreshold()."\n\n";

// Example 4: Pre-configure services
echo "Example 4: Pre-configure services\n";
echo "----------------------------------\n";

// Configure different services with different thresholds
Breaker::configure('critical-service', [
    'failure_threshold' => 2,
    'timeout' => 120,
]);

Breaker::configure('non-critical-service', [
    'failure_threshold' => 10,
    'timeout' => 30,
]);

echo "Critical service configured with failure_threshold=2\n";
echo "Non-critical service configured with failure_threshold=10\n\n";

// Example 5: Working with multiple services
echo "Example 5: Multiple services\n";
echo "----------------------------\n";

$services = ['user-api', 'product-api', 'inventory-api'];

foreach ($services as $service) {
    $result = Breaker::protect(
        $service,
        fn () => ['service' => $service, 'status' => 'healthy']
    );

    echo "{$service}: ".json_encode($result)."\n";
}

echo "\nAll managed services: ".implode(', ', array_keys(Breaker::all()))."\n\n";

// Example 6: Health monitoring
echo "Example 6: Health monitoring\n";
echo "----------------------------\n";

$healthReports = Breaker::healthReport();

foreach ($healthReports as $service => $health) {
    echo "{$service}: {$health->status->value} - {$health->message}\n";
}

echo "\n";

// Example 7: Specific service health
echo "Example 7: Get health for specific service\n";
echo "-------------------------------------------\n";

$health = Breaker::healthReport('api-service');
echo "Service: api-service\n";
echo "Status: {$health->status->value}\n";
echo "State: {$health->state}\n";
echo "Failures: {$health->failureCount}/{$health->failureThreshold}\n\n";

// Example 8: Get instance for multiple calls
echo "Example 8: Reusable instance for multiple calls\n";
echo "------------------------------------------------\n";

$apiBreaker = Breaker::instance('external-api', ['failure_threshold' => 5]);

for ($i = 1; $i <= 3; $i++) {
    try {
        $result = $apiBreaker->call(function () use ($i) {
            echo "API call #{$i}...\n";
            if ($i === 2) {
                throw new Exception('Network timeout');
            }

            return "Success #{$i}";
        });
        echo "Result: {$result}\n";
    } catch (Exception $e) {
        echo "Failed: {$e->getMessage()}\n";
    }
}

echo "Failure count: {$apiBreaker->getFailureCount()}\n";
echo "State: {$apiBreaker->getState()}\n\n";

// Example 9: Demonstrating circuit opening
echo "Example 9: Circuit breaker opening\n";
echo "-----------------------------------\n";

// Trip the circuit with failures
for ($i = 1; $i <= 6; $i++) {
    try {
        Breaker::protect('flaky-service', function () {
            throw new Exception('Service unavailable');
        });
    } catch (CircuitOpenException $e) {
        echo "Attempt #{$i}: Circuit is OPEN - failing fast\n";
        break;
    } catch (Exception $e) {
        echo "Attempt #{$i}: Failed - {$e->getMessage()}\n";
    }
}

$breaker = Breaker::instance('flaky-service');
echo "Final state: {$breaker->getState()}\n";
echo "Failures: {$breaker->getFailureCount()}/{$breaker->getFailureThreshold()}\n\n";

// Example 10: Fallback handles circuit open state
echo "Example 10: Fallback with open circuit\n";
echo "---------------------------------------\n";

$result = Breaker::protect(
    'flaky-service',
    function () {
        return 'This will not execute - circuit is open';
    },
    fallback: function ($exception, $breaker) {
        echo "Circuit state: {$breaker->getState()}\n";
        echo "Using cached data as fallback\n";

        return ['cached' => true, 'data' => 'stale data'];
    }
);

echo 'Result: '.json_encode($result)."\n\n";

// Example 11: Clean up specific service
echo "Example 11: Managing instances\n";
echo "------------------------------\n";

echo 'Before cleanup: '.count(Breaker::all())." services\n";

Breaker::forget('flaky-service');
echo "After forgetting 'flaky-service': ".count(Breaker::all())." services\n\n";

echo "=== Examples Complete ===\n";
echo "\nKey Takeaways:\n";
echo "1. One-liner API: Breaker::protect('service', fn() => call())\n";
echo "2. Automatic instance management - no manual setup\n";
echo "3. Built-in fallback support\n";
echo "4. Pre-configure services with different thresholds\n";
echo "5. Health monitoring across all services\n";
echo "6. Clean, Laravel-inspired API\n";

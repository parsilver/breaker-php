<?php

require_once __DIR__.'/../vendor/autoload.php';

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;

// Create a circuit breaker with default settings
$breaker = new CircuitBreaker('api-service');

// Simulate a service call
function callExternalService($shouldFail = false)
{
    if ($shouldFail) {
        throw new Exception('Service is unavailable');
    }

    return 'Service response data';
}

// Example usage with successful call
try {
    $result = $breaker->call(function () {
        return callExternalService(false);
    });

    echo 'Success: '.$result."\n";
    echo 'Current state: '.$breaker->getState()."\n";
} catch (CircuitOpenException $e) {
    echo "Circuit is open - failing fast\n";
} catch (Exception $e) {
    echo 'Service error: '.$e->getMessage()."\n";
}

echo "\n";

// Example usage with failed calls to trigger open state
try {
    // We'll make several failing calls to trigger the circuit breaker
    for ($i = 0; $i < 10; $i++) {
        try {
            $result = $breaker->call(function () {
                return callExternalService(true); // This will fail
            });
        } catch (CircuitOpenException $e) {
            echo 'Circuit is open - failing fast on attempt '.($i + 1)."\n";
            break;
        } catch (Exception $e) {
            echo 'Service error on attempt '.($i + 1).': '.$e->getMessage()."\n";
        }
    }

    echo 'Current state: '.$breaker->getState()."\n";
} catch (Exception $e) {
    echo 'Unexpected error: '.$e->getMessage()."\n";
}

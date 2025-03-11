<?php

require_once __DIR__.'/../vendor/autoload.php';

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;
use Farzai\Breaker\Storage\FileStorage;

// Create a storage directory
$storageDir = __DIR__.'/storage';
if (! is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

// Create a file storage instance
$storage = new FileStorage($storageDir);

// Create a circuit breaker with file storage
$breaker = new CircuitBreaker('persistent-service', [
    'failure_threshold' => 3,
    'timeout' => 5,
    'success_threshold' => 2,
], $storage);

// Simulate a service call
function callService($shouldFail = false)
{
    if ($shouldFail) {
        throw new Exception('Service is unavailable');
    }

    return 'Service response data';
}

// Display the current state
echo 'Circuit state: '.$breaker->getState()."\n";

// Example usage
try {
    // Get user input on whether the call should fail
    echo 'Should the call fail? (y/n): ';
    $shouldFail = trim(fgets(STDIN)) === 'y';

    $result = $breaker->call(function () use ($shouldFail) {
        return callService($shouldFail);
    });

    echo 'Success: '.$result."\n";
} catch (CircuitOpenException $e) {
    echo "Circuit is open - failing fast\n";
    echo 'Try again in '.$breaker->getTimeout()." seconds.\n";
} catch (Exception $e) {
    echo 'Service error: '.$e->getMessage()."\n";
}

echo 'Circuit state after call: '.$breaker->getState()."\n";
echo "This state will be persisted between script runs.\n";

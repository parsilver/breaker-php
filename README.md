# Circuit Breaker - PHP


[![Latest Version on Packagist](https://img.shields.io/packagist/v/farzai/breaker.svg?style=flat-square)](https://packagist.org/packages/farzai/breaker)
[![Tests](https://img.shields.io/github/actions/workflow/status/parsilver/breaker-php/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/parsilver/breaker-php/actions/workflows/run-tests.yml)
[![codecov](https://codecov.io/gh/parsilver/breaker-php/branch/main/graph/badge.svg)](https://codecov.io/gh/parsilver/breaker-php)
[![Total Downloads](https://img.shields.io/packagist/dt/farzai/breaker.svg?style=flat-square)](https://packagist.org/packages/farzai/breaker)


A PHP Circuit Breaker implementation for building resilient applications.

## Installation

```bash
composer require farzai/breaker
```

## Requirements

- PHP 8.2 or higher

## Basic Usage

```php
use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;

// Create a circuit breaker with default settings
$breaker = new CircuitBreaker('service-name');

try {
    $result = $breaker->call(function () {
        // Your code that might fail
        return callExternalService();
    });
} catch (CircuitOpenException $e) {
    // Circuit is open due to previous failures
    return getBackupData();
} catch (\Exception $e) {
    // Handle other exceptions
    return null;
}
```

## Advanced Configuration

```php
// Create a circuit breaker with custom configuration
$breaker = new CircuitBreaker('service-name', [
    'failure_threshold' => 5,      // Number of failures before opening circuit
    'timeout' => 30,               // Seconds to wait before checking if service is back
    'success_threshold' => 2,      // Success calls needed to close the circuit again
]);
```

## Using Fallbacks

```php
use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;

$breaker = new CircuitBreaker('service-name');

// Execute with a fallback
$result = $breaker->callWithFallback(
    function () {
        // Primary function that might fail
        return callExternalService();
    },
    function ($exception, $circuitBreaker) {
        // Fallback function with access to the exception and circuit breaker
        if ($exception instanceof CircuitOpenException) {
            // Circuit is open, use cached data or alternative service
            return getCachedData();
        }
        
        // Handle other types of failures
        return getDefaultResponse();
    }
);
```

## Event Listeners

```php
use Farzai\Breaker\CircuitBreaker;

$breaker = new CircuitBreaker('service-name');

// Listen to state transitions
$breaker->onStateChange(function ($newState, $oldState, $circuitBreaker) {
    echo "Circuit state changed from {$oldState} to {$newState}";
});

// Listen to specific state transitions
$breaker->onOpen(function ($circuitBreaker) {
    // Circuit opened - notify administrators
    sendAlertToAdmin("Service {$circuitBreaker->getServiceKey()} is down!");
});

$breaker->onHalfOpen(function ($circuitBreaker) {
    // Circuit is testing the service
    logEvent("Testing if service is back online");
});

$breaker->onClose(function ($circuitBreaker) {
    // Circuit closed - service is healthy again
    sendStatusUpdate("Service recovered successfully");
});

// Listen to call success and failures
$breaker->onSuccess(function ($result, $circuitBreaker) {
    // Track successful calls
    incrementMetric("service.success");
});

// Remove a listener when no longer needed
$listenerId = $breaker->onSuccess(function () { /* ... */ });
$breaker->removeListener($listenerId);
```

## Persistent Storage

By default, circuit state is stored in memory. For production, use file storage:

```php
use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Storage\FileStorage;

// Create a file storage with a specific directory
$storage = new FileStorage(__DIR__ . '/storage/circuit-breaker');

// Pass storage to circuit breaker
$breaker = new CircuitBreaker('service-name', [], $storage);
```

## Circuit Breaker States

The circuit breaker operates in three states:

1. **CLOSED**: All requests pass through to the service. This is the default state when everything is working normally.
2. **OPEN**: Requests fail fast without calling the service. This happens after the failure threshold is reached, protecting your system from cascading failures.
3. **HALF-OPEN**: After the timeout period, the circuit allows a limited number of test requests to check if the service has recovered.

## Best Practices

### Service Isolation

Create separate circuit breakers for different services:

```php
$userServiceBreaker = new CircuitBreaker('user-service');
$paymentServiceBreaker = new CircuitBreaker('payment-service');
$notificationServiceBreaker = new CircuitBreaker('notification-service');
```

### Appropriate Thresholds

Configure thresholds based on service characteristics:

```php
// Critical service with stricter thresholds
$criticalBreaker = new CircuitBreaker('critical-service', [
    'failure_threshold' => 2,      // Open after just 2 failures
    'timeout' => 60,               // Wait longer before testing again
    'success_threshold' => 3,      // Require more successes to restore
]);

// Non-critical service with more lenient settings
$nonCriticalBreaker = new CircuitBreaker('non-critical-service', [
    'failure_threshold' => 10,     // Allow more failures
    'timeout' => 15,               // Test again quickly
    'success_threshold' => 1,      // Close after first success
]);
```

### Always Use Fallbacks

Always provide fallbacks for critical operations:

```php
$result = $breaker->callWithFallback(
    function () {
        return fetchDataFromPrimarySource();
    },
    function ($exception) {
        if ($exception instanceof CircuitOpenException) {
            return fetchFromCache();
        }
        // For network errors, use backup service
        return fetchFromBackupService();
    }
);
```

### Monitoring Circuit Health

Use event listeners to monitor circuit health:

```php
// Log all state transitions
$breaker->onStateChange(function ($newState, $oldState, $breaker) use ($logger) {
    $logger->info("Circuit '{$breaker->getServiceKey()}' changed from {$oldState} to {$newState}");
});

// Alert on circuit open
$breaker->onOpen(function ($breaker) use ($alertService) {
    $alertService->sendAlert(
        "Circuit breaker for {$breaker->getServiceKey()} has opened after " . 
        "{$breaker->getFailureThreshold()} consecutive failures."
    );
});
```

### Circuit Breaker Pattern Integration

Integrate circuit breakers within your application architecture:

```php
class ApiClient
{
    private $httpClient;
    private $circuitBreaker;
    private $cache;
    
    public function __construct(HttpClientInterface $httpClient, CacheInterface $cache)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->circuitBreaker = new CircuitBreaker('api-client');
        
        // Set up monitoring
        $this->setupEventListeners();
    }
    
    public function fetchData($id)
    {
        return $this->circuitBreaker->callWithFallback(
            function () use ($id) {
                $response = $this->httpClient->get("/data/{$id}");
                
                // Cache successful responses
                $this->cache->set("data_{$id}", $response, 3600);
                
                return $response;
            },
            function ($exception) use ($id) {
                // Return cached data as fallback
                return $this->cache->get("data_{$id}");
            }
        );
    }
    
    private function setupEventListeners()
    {
        // Add event listeners for monitoring
    }
}
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE.md) file for details.
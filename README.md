# Farzai Breaker

A PHP Circuit Breaker implementation for building resilient applications.

## Installation

```bash
composer require farzai/breaker
```

## Requirements

- PHP 8.2 or higher

## Usage

```php
use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\States\ClosedState;

// Create a circuit breaker with default settings
$breaker = new CircuitBreaker('service-name');

// Or with custom configuration
$breaker = new CircuitBreaker('service-name', [
    'failure_threshold' => 5,      // How many failures before opening
    'timeout' => 30,               // Seconds to wait before checking if service is back
    'success_threshold' => 2,      // Success calls needed to close the circuit again
]);

try {
    $result = $breaker->call(function () {
        // Your code that might fail
        return callExternalService();
    });
} catch (\Farzai\Breaker\Exceptions\CircuitBreakerException $e) {
    // Handle circuit breaker specific exceptions
}
```

## State Transitions

The circuit breaker operates in three states:

1. **CLOSED**: All requests pass through to the service.
2. **OPEN**: Requests fail fast without calling the service.
3. **HALF-OPEN**: A limited number of test requests are allowed through to check if the service is back.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
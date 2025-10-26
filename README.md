# Circuit Breaker - PHP


[![Latest Version on Packagist](https://img.shields.io/packagist/v/farzai/breaker.svg?style=flat-square)](https://packagist.org/packages/farzai/breaker)
[![Tests](https://img.shields.io/github/actions/workflow/status/parsilver/breaker-php/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/parsilver/breaker-php/actions/workflows/run-tests.yml)
[![codecov](https://codecov.io/gh/parsilver/breaker-php/branch/main/graph/badge.svg)](https://codecov.io/gh/parsilver/breaker-php)
[![Total Downloads](https://img.shields.io/packagist/dt/farzai/breaker.svg?style=flat-square)](https://packagist.org/packages/farzai/breaker)


A production-ready PHP Circuit Breaker implementation for building resilient applications with **PSR-14 compliant event system**, type-safe event objects, and comprehensive monitoring capabilities.

## Features

- **Circuit Breaker Pattern** - Protect your application from cascading failures
- **Auto-managed Instances** - Service registry with automatic instance management
- **PSR-14 Event System** - Type-safe, immutable event objects with priority support
- **Event Subscribers** - Organize event listeners with subscriber pattern
- **Built-in Monitoring** - LoggingSubscriber and MetricsSubscriber included
- **Performance Tracking** - Execution time metrics for all operations
- **Exception-Safe** - Event listeners won't break your circuit breaker
- **Advanced Storage** - File, Memory, PSR-16 (Redis/Memcached), with decorators and fallback chains
- **Flexible Configuration** - Customizable thresholds and timeouts

## Installation

```bash
composer require farzai/breaker
```

## Requirements

- PHP 8.2 or higher
- `psr/event-dispatcher ^1.0` (automatically installed)
- `psr/log ^3.0` for logging features (automatically installed)
- `psr/simple-cache ^3.0` for PSR-16 cache integration (automatically installed)

## Basic Usage

The easiest way to get started is using the `Breaker` facade, which provides a clean, Laravel-inspired API:

```php
use Farzai\Breaker\Breaker;

// Simple one-liner protection
$result = Breaker::protect('api-service', function () {
    return callExternalApi();
});

// With automatic fallback
$result = Breaker::protect(
    'api-service',
    fn() => callExternalApi(),
    fallback: fn($e) => getCachedData()
);

// With custom configuration
$result = Breaker::protect(
    'api-service',
    fn() => callExternalApi(),
    config: ['failure_threshold' => 3, 'timeout' => 60]
);
```

**Why use the Breaker facade?**
- ✅ **One-liner API** - No manual setup required
- ✅ **Auto-managed instances** - Circuit breakers created and cached automatically
- ✅ **Zero boilerplate** - Just protect your calls and go
- ✅ **Clean, familiar syntax** - Laravel-style API that feels natural

## Configuring Services

```php
use Farzai\Breaker\Breaker;

// Configure different services with different thresholds
Breaker::configure('critical-service', [
    'failure_threshold' => 2,
    'timeout' => 120,
]);

Breaker::configure('non-critical-service', [
    'failure_threshold' => 10,
    'timeout' => 30,
]);

// Use pre-configured services
$result = Breaker::protect('critical-service', fn() => callCriticalApi());
```

## Health Monitoring

```php
// Get health for all services
$reports = Breaker::healthReport();
foreach ($reports as $service => $health) {
    echo "{$service}: {$health->status->value}\n";
}

// Get health for specific service
$health = Breaker::healthReport('api-service');
echo "Status: {$health->status->value}\n";
echo "Failures: {$health->failureCount}/{$health->failureThreshold}\n";
```

## Dependency Injection (Recommended for Production)

For production applications, use dependency injection for better testability:

```php
use Farzai\Breaker\BreakerManager;

class ApiClient
{
    public function __construct(private BreakerManager $breaker) {}

    public function fetchData(): array
    {
        return $this->breaker->protect(
            'api-service',
            fn() => $this->callApi(),
            fallback: fn($e) => $this->getCached()
        );
    }
}

// In your DI container
$manager = new BreakerManager();
$apiClient = new ApiClient($manager);
```

**💡 Tip:** The facade (`Breaker::protect()`) is perfect for quick scripts and simple use cases. For production services, inject `BreakerManager` for better testability.

See [examples/facade_usage.php](examples/facade_usage.php) and [examples/dependency_injection.php](examples/dependency_injection.php) for complete examples.

## Event Listeners

Monitor circuit breaker events to track failures, state changes, and performance:

```php
use Farzai\Breaker\Breaker;
use Farzai\Breaker\Events\CircuitStateChangedEvent;
use Farzai\Breaker\Events\CircuitOpenedEvent;
use Farzai\Breaker\Events\CallSucceededEvent;
use Farzai\Breaker\Events\CallFailedEvent;

// Get the managed instance to add listeners
$breaker = Breaker::instance('api-service');

// Listen to state transitions with typed event objects
$breaker->onStateChange(function (CircuitStateChangedEvent $event) {
    echo sprintf(
        "Circuit state changed from %s to %s for service %s",
        $event->getPreviousState(),
        $event->getNewState(),
        $event->getServiceKey()
    );
});

// Listen to specific state transitions
$breaker->onOpen(function (CircuitOpenedEvent $event) {
    // Circuit opened - notify administrators with rich context
    sendAlertToAdmin([
        'service' => $event->getServiceKey(),
        'failure_count' => $event->getFailureCount(),
        'failure_threshold' => $event->getFailureThreshold(),
        'timeout' => $event->getTimeout(),
        'half_open_at' => date('Y-m-d H:i:s', $event->getHalfOpenTimestamp()),
    ]);
});

$breaker->onHalfOpen(function (CircuitHalfOpenedEvent $event) {
    logEvent("Testing if service is back online", [
        'success_threshold' => $event->getSuccessThreshold(),
    ]);
});

$breaker->onClose(function (CircuitClosedEvent $event) {
    // Check if this is a recovery or initial close
    $message = $event->isRecovery()
        ? 'Service recovered successfully'
        : 'Service initialized';
    sendStatusUpdate($message);
});

// Listen to call success with execution metrics
$breaker->onSuccess(function (CallSucceededEvent $event) {
    incrementMetric('service.success', [
        'execution_time' => $event->getExecutionTime(), // milliseconds
        'state' => $event->getCurrentState(),
        'result' => $event->getResult(),
    ]);
});

// Listen to failures with detailed error info
$breaker->onFailure(function (CallFailedEvent $event) {
    if ($event->willTriggerOpen()) {
        sendCriticalAlert('Circuit about to open!');
    }

    logError($event->getExceptionMessage(), [
        'exception_class' => $event->getExceptionClass(),
        'execution_time' => $event->getExecutionTime(),
        'failure_count' => $event->getFailureCount(),
        'threshold' => $event->getFailureThreshold(),
    ]);
});

// Remove a listener when no longer needed
$listenerId = $breaker->onSuccess(function ($event) { /* ... */ });
$breaker->removeListener($listenerId);
```

### Event Priority

Control the execution order of listeners using priorities (higher = earlier):

```php
// High priority listener executes first
$breaker->onFailure(function (CallFailedEvent $event) {
    criticalErrorHandler($event);
}, priority: 100);

// Normal priority listener
$breaker->onFailure(function (CallFailedEvent $event) {
    logError($event);
}, priority: 0);

// Low priority listener executes last
$breaker->onFailure(function (CallFailedEvent $event) {
    sendMetrics($event);
}, priority: -10);
```

### Event Propagation Control

Stop event propagation to prevent subsequent listeners from executing:

```php
$breaker->onFailure(function (CallFailedEvent $event) {
    if ($event->willTriggerOpen()) {
        handleCriticalFailure($event);

        // Stop other failure listeners from executing
        $event->stopPropagation();
    }
});
```

## Event Subscribers

For better organization, group related event listeners into subscriber classes:

```php
use Farzai\Breaker\Events\EventSubscriberInterface;
use Farzai\Breaker\Events\CallSucceededEvent;
use Farzai\Breaker\Events\CallFailedEvent;
use Farzai\Breaker\Events\CircuitOpenedEvent;

class MetricsCollectorSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CallSucceededEvent::class => 'onSuccess',
            CallFailedEvent::class => ['method' => 'onFailure', 'priority' => 100],
            CircuitOpenedEvent::class => 'onOpen',
        ];
    }

    public function onSuccess(CallSucceededEvent $event): void
    {
        $this->metrics->increment('circuit.success', [
            'service' => $event->getServiceKey(),
            'execution_time' => $event->getExecutionTime(),
        ]);
    }

    public function onFailure(CallFailedEvent $event): void
    {
        $this->metrics->increment('circuit.failure', [
            'service' => $event->getServiceKey(),
            'exception' => $event->getExceptionClass(),
        ]);
    }

    public function onOpen(CircuitOpenedEvent $event): void
    {
        $this->metrics->increment('circuit.opened', [
            'service' => $event->getServiceKey(),
        ]);
    }
}

// Add the subscriber to your circuit breaker
$breaker->addSubscriber(new MetricsCollectorSubscriber($metrics));
```

### Built-in Subscribers

The library provides ready-to-use subscribers:

#### LoggingSubscriber

Automatically logs all circuit breaker events using PSR-3 logger:

```php
use Farzai\Breaker\Events\Subscribers\LoggingSubscriber;
use Psr\Log\LogLevel;

// With default log level (INFO)
$breaker->addSubscriber(new LoggingSubscriber($logger));

// With custom log level
$breaker->addSubscriber(new LoggingSubscriber($logger, LogLevel::WARNING));
```

#### MetricsSubscriber

Automatically collects metrics for all events:

```php
use Farzai\Breaker\Events\Subscribers\MetricsSubscriber;
use Farzai\Breaker\Metrics\InMemoryMetricsCollector;

$metricsCollector = new InMemoryMetricsCollector();
$breaker->addSubscriber(new MetricsSubscriber($metricsCollector));

// Later, retrieve collected metrics
$metrics = $metricsCollector->getMetrics();
echo "Success rate: {$metrics->getSuccessRate()}%";
```

## Storage Architecture

The circuit breaker features a **modern, pattern-based storage layer** with multiple design patterns for flexibility and scalability.

### Quick Start

By default, circuit state is stored in memory. For production, use persistent file storage:

```php
use Farzai\Breaker\Breaker;
use Farzai\Breaker\Storage\StorageFactory;

// Create file storage repository
$repository = StorageFactory::createRepository(
    StorageFactory::file(__DIR__ . '/storage/circuit-breaker')
);

// Set as default for all circuit breakers
Breaker::setDefaultRepository($repository);

// Now all protected calls use persistent storage
$result = Breaker::protect('api-service', fn() => callApi());
```

### Available Storage Adapters

#### File Storage
Persistent filesystem storage with atomic writes:

```php
$repository = StorageFactory::createRepository(
    StorageFactory::file('/tmp/circuit-breaker')
);
```

#### In-Memory Storage
Fast, non-persistent storage for testing or short-lived processes:

```php
$repository = StorageFactory::createRepository(
    StorageFactory::memory()
);
```

#### PSR-16 Cache Adapter
Use any PSR-16 cache implementation (Redis, Memcached, etc.):

```php
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

$redis = RedisAdapter::createConnection('redis://localhost');
$cache = new Psr16Cache(new RedisAdapter($redis));

$repository = StorageFactory::createRepository(
    StorageFactory::psr16($cache, defaultTtl: 3600)
);
```

### Advanced Storage Patterns

#### With Decorators (Logging, Metrics, Retry)

```php
use Psr\Log\NullLogger;

// Use fluent builder for complex configurations
$storage = StorageFactory::builder('file', ['path' => '/tmp'])
    ->withLogging(new NullLogger())      // Add logging
    ->withRetry(maxAttempts: 3)          // Add retry logic
    ->build();

$repository = StorageFactory::createRepository($storage);
```

#### High Availability with Fallback Chain

```php
// Create fallback chain: Redis → File → Memory
$storage = StorageFactory::fallback([
    StorageFactory::psr16($redisCache),     // Primary: Redis
    StorageFactory::file('/tmp'),            // Secondary: File
    StorageFactory::memory(),                // Tertiary: Memory
]);

$repository = StorageFactory::createRepository($storage);
```

### Storage Features

✅ **SHA-256 Key Hashing** - Prevents service key collisions
✅ **Atomic Writes** - Prevents data corruption
✅ **File Locking** - Thread-safe concurrent access
✅ **Automatic Cleanup** - Orphaned temp file detection
✅ **PSR-16 Support** - Redis, Memcached, any cache
✅ **Decorator Pattern** - Composable logging, metrics, retry
✅ **Fallback Chain** - High availability
✅ **TTL Support** - Automatic expiration

## Circuit Breaker States

The circuit breaker operates in three states:

1. **CLOSED**: All requests pass through to the service. This is the default state when everything is working normally.
2. **OPEN**: Requests fail fast without calling the service. This happens after the failure threshold is reached, protecting your system from cascading failures.
3. **HALF-OPEN**: After the timeout period, the circuit allows a limited number of test requests to check if the service has recovered.

## Best Practices

### Service Isolation

Configure different services with appropriate thresholds:

```php
use Farzai\Breaker\Breaker;

// Configure each service based on criticality
Breaker::configure('user-service', [
    'failure_threshold' => 5,
    'timeout' => 30,
]);

Breaker::configure('payment-service', [
    'failure_threshold' => 2,  // Critical - fail fast
    'timeout' => 60,
]);

Breaker::configure('notification-service', [
    'failure_threshold' => 10,  // Non-critical - more lenient
    'timeout' => 15,
]);
```

### Appropriate Thresholds

Configure thresholds based on service characteristics:

```php
// Critical service with stricter thresholds
Breaker::configure('critical-service', [
    'failure_threshold' => 2,      // Open after just 2 failures
    'timeout' => 60,               // Wait longer before testing again
    'success_threshold' => 3,      // Require more successes to restore
]);

// Non-critical service with more lenient settings
Breaker::configure('non-critical-service', [
    'failure_threshold' => 10,     // Allow more failures
    'timeout' => 15,               // Test again quickly
    'success_threshold' => 1,      // Close after first success
]);
```

### Always Use Fallbacks

Always provide fallbacks for critical operations:

```php
use Farzai\Breaker\Breaker;

$result = Breaker::protect(
    'primary-api',
    fn() => fetchDataFromPrimarySource(),
    fallback: fn($e) => $e instanceof CircuitOpenException
        ? fetchFromCache()
        : fetchFromBackupService()
);
```

### Monitoring Circuit Health

Use event subscribers for comprehensive monitoring:

```php
use Farzai\Breaker\Events\Subscribers\LoggingSubscriber;

// Use built-in logging subscriber for automatic logging
$breaker->addSubscriber(new LoggingSubscriber($logger));

// Or create custom monitoring with event listeners
$breaker->onStateChange(function (CircuitStateChangedEvent $event) use ($logger) {
    $logger->info("Circuit '{$event->getServiceKey()}' changed", [
        'previous_state' => $event->getPreviousState(),
        'new_state' => $event->getNewState(),
        'timestamp' => $event->getTimestamp(),
    ]);
});

// Alert on circuit open with rich context
$breaker->onOpen(function (CircuitOpenedEvent $event) use ($alertService) {
    $alertService->sendAlert([
        'title' => "Circuit breaker opened",
        'service' => $event->getServiceKey(),
        'failures' => "{$event->getFailureCount()}/{$event->getFailureThreshold()}",
        'retry_at' => date('Y-m-d H:i:s', $event->getHalfOpenTimestamp()),
        'severity' => 'critical',
    ]);
});

// Track execution metrics
$breaker->onSuccess(function (CallSucceededEvent $event) use ($metrics) {
    $metrics->histogram('circuit.execution_time', $event->getExecutionTime(), [
        'service' => $event->getServiceKey(),
        'state' => $event->getCurrentState(),
    ]);
});
```

### Circuit Breaker Pattern Integration

Integrate circuit breakers within your application architecture using dependency injection:

```php
use Farzai\Breaker\BreakerManager;
use Farzai\Breaker\Events\CallSucceededEvent;
use Farzai\Breaker\Events\CircuitOpenedEvent;
use Farzai\Breaker\Events\Subscribers\LoggingSubscriber;

class ApiClient
{
    private HttpClientInterface $httpClient;
    private BreakerManager $breaker;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        BreakerManager $breaker,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->breaker = $breaker;
        $this->cache = $cache;
        $this->logger = $logger;

        // Set up monitoring with subscribers
        $this->setupMonitoring();
    }

    public function fetchData($id)
    {
        return $this->breaker->protect(
            'api-client',
            function () use ($id) {
                $response = $this->httpClient->get("/data/{$id}");

                // Cache successful responses
                $this->cache->set("data_{$id}", $response, 3600);

                return $response;
            },
            fallback: fn($exception) => $this->cache->get("data_{$id}")
        );
    }

    private function setupMonitoring(): void
    {
        $circuitBreaker = $this->breaker->instance('api-client');

        // Add built-in logging subscriber
        $circuitBreaker->addSubscriber(
            new LoggingSubscriber($this->logger)
        );

        // Add custom event listeners for specific actions
        $circuitBreaker->onSuccess(function (CallSucceededEvent $event) {
            if ($event->getExecutionTime() > 1000) {
                $this->logger->warning('Slow API response detected', [
                    'execution_time' => $event->getExecutionTime(),
                    'threshold' => 1000,
                ]);
            }
        });

        $circuitBreaker->onOpen(function (CircuitOpenedEvent $event) {
            // Send alert to monitoring system
            $this->sendAlert('API Circuit Opened', [
                'failures' => $event->getFailureCount(),
                'retry_at' => date('Y-m-d H:i:s', $event->getHalfOpenTimestamp()),
            ]);
        });
    }

    private function sendAlert(string $title, array $context): void
    {
        // Implementation for sending alerts
    }
}
```

## Event System Features

### PSR-14 Compliance

The event system is fully **PSR-14 compliant**, making it compatible with standard PHP event dispatching tools and libraries. All event objects and the event dispatching mechanism follow the PSR-14 standard for event dispatcher interfaces.

### Available Event Types

All events extend `AbstractCircuitEvent` and provide:

| Event Class | When Fired | Key Methods |
|-------------|------------|-------------|
| `CircuitStateChangedEvent` | Any state transition | `getPreviousState()`, `getNewState()`, `isTransitionToOpen()` |
| `CircuitOpenedEvent` | Circuit transitions to open | `getFailureCount()`, `getFailureThreshold()`, `getHalfOpenTimestamp()` |
| `CircuitClosedEvent` | Circuit transitions to closed | `getPreviousState()`, `isRecovery()` |
| `CircuitHalfOpenedEvent` | Circuit transitions to half-open | `getSuccessThreshold()` |
| `CallSucceededEvent` | Protected call succeeds | `getResult()`, `getExecutionTime()` |
| `CallFailedEvent` | Protected call fails | `getException()`, `getExecutionTime()`, `willTriggerOpen()` |
| `FallbackExecutedEvent` | Fallback is executed | `getResult()`, `getOriginalException()`, `getExecutionTime()` |

All events provide common methods:
- `getCircuitBreaker()` - Get the circuit breaker instance
- `getServiceKey()` - Get the service identifier
- `getCurrentState()` - Get current circuit state
- `getTimestamp()` - Get event timestamp
- `stopPropagation()` - Stop event from reaching other listeners
- `isPropagationStopped()` - Check if propagation was stopped

### Exception-Safe Dispatching

Event listeners are **exception-safe** - if a listener throws an exception, it won't break the circuit breaker:

```php
use Farzai\Breaker\Events\EventDispatcher;

$breaker->getEventDispatcher()->setErrorHandlingStrategy(
    EventDispatcher::ERROR_STRATEGY_SILENT  // Log errors and continue (default)
    // EventDispatcher::ERROR_STRATEGY_COLLECT  // Collect errors for later inspection
    // EventDispatcher::ERROR_STRATEGY_STOP     // Stop on first error
);

// Even if a listener fails, the circuit breaker continues working
$breaker->onSuccess(function ($event) {
    throw new \Exception('Listener error!');
    // This won't break the circuit breaker
});
```

### Execution Metrics

Track performance with built-in execution time tracking:

```php
$breaker->onSuccess(function (CallSucceededEvent $event) {
    $executionTime = $event->getExecutionTime(); // in milliseconds

    if ($executionTime > 500) {
        $this->logger->warning('Slow response detected', [
            'service' => $event->getServiceKey(),
            'execution_time' => $executionTime,
        ]);
    }
});

$breaker->onFailure(function (CallFailedEvent $event) {
    // Track how long it took to fail
    $this->metrics->timing('failure_time', $event->getExecutionTime());
});
```

### Immutable Event Objects

All event objects are **immutable** - their state cannot be changed after creation, ensuring data consistency:

```php
$breaker->onSuccess(function (CallSucceededEvent $event) {
    $result = $event->getResult();        // ✓ Reading is allowed
    $time = $event->getExecutionTime();   // ✓ Reading is allowed

    // Event properties are readonly - cannot be modified
});
```

## Manual Usage (Advanced)

For advanced use cases where you need fine-grained control, you can manually instantiate `CircuitBreaker` instances:

```php
use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;
use Farzai\Breaker\Storage\StorageFactory;

// Create with custom configuration and storage
$repository = StorageFactory::createRepository(
    StorageFactory::file(__DIR__ . '/storage/circuit-breaker')
);

$breaker = new CircuitBreaker(
    serviceKey: 'my-service',
    config: [
        'failure_threshold' => 5,
        'timeout' => 30,
        'success_threshold' => 2,
    ],
    repository: $repository,
    logger: $logger,  // Optional PSR-3 logger
);

// Use the circuit breaker
try {
    $result = $breaker->call(function () {
        return callExternalService();
    });
} catch (CircuitOpenException $e) {
    return getBackupData();
}

// Or with fallback
$result = $breaker->callWithFallback(
    fn() => callExternalService(),
    fn($e, $breaker) => getBackupData()
);
```

**When to use manual instantiation:**
- You need custom time providers for testing
- You require specific logger or repository instances per breaker
- You're building a library that wraps the circuit breaker
- You need to pass the breaker instance to multiple collaborators

**For most use cases, we recommend using the `Breaker` facade or `BreakerManager` instead.**

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE.md) file for details.
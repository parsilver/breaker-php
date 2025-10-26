<?php

require_once __DIR__.'/../vendor/autoload.php';

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Events\CallFailedEvent;
use Farzai\Breaker\Events\CallSucceededEvent;
use Farzai\Breaker\Events\CircuitOpenedEvent;
use Farzai\Breaker\Events\EventSubscriberInterface;
use Farzai\Breaker\Events\Subscribers\LoggingSubscriber;
use Psr\Log\AbstractLogger;

// Create a custom logger
$logger = new class extends AbstractLogger
{
    public function log($level, $message, array $context = []): void
    {
        echo "[$level] $message\n";
        if (! empty($context)) {
            echo '  Context: '.json_encode($context, JSON_PRETTY_PRINT)."\n";
        }
    }
};

// Create a custom event subscriber
class MetricsCollectorSubscriber implements EventSubscriberInterface
{
    private int $successCount = 0;

    private int $failureCount = 0;

    private int $openCount = 0;

    public static function getSubscribedEvents(): array
    {
        return [
            CallSucceededEvent::class => ['method' => 'onSuccess', 'priority' => 100],
            CallFailedEvent::class => ['method' => 'onFailure', 'priority' => 100],
            CircuitOpenedEvent::class => ['method' => 'onOpen', 'priority' => 100],
        ];
    }

    public function onSuccess(CallSucceededEvent $event): void
    {
        $this->successCount++;
        echo "✓ Success! Total successes: {$this->successCount} (execution time: {$event->getExecutionTime()}ms)\n";
    }

    public function onFailure(CallFailedEvent $event): void
    {
        $this->failureCount++;
        echo "✗ Failure! Total failures: {$this->failureCount} (error: {$event->getExceptionMessage()})\n";
    }

    public function onOpen(CircuitOpenedEvent $event): void
    {
        $this->openCount++;
        echo "⚠️  Circuit opened! Total opens: {$this->openCount}\n";
    }

    public function getStats(): array
    {
        return [
            'successes' => $this->successCount,
            'failures' => $this->failureCount,
            'circuit_opens' => $this->openCount,
        ];
    }
}

// Create circuit breaker
$breaker = new CircuitBreaker('api-service', [
    'failure_threshold' => 2,
    'success_threshold' => 1,
    'timeout' => 1,
]);

echo "=== Circuit Breaker with Event Subscribers Example ===\n\n";

// Add built-in logging subscriber
echo "Adding LoggingSubscriber...\n";
$breaker->addSubscriber(new LoggingSubscriber($logger));

// Add custom metrics subscriber
echo "Adding MetricsCollectorSubscriber...\n";
$metricsSubscriber = new MetricsCollectorSubscriber;
$breaker->addSubscriber($metricsSubscriber);

echo "\n--- Running Tests ---\n\n";

// Test 1: Successful call
echo "Test 1: Successful call\n";
$breaker->call(function () {
    return ['status' => 'ok', 'data' => 'example'];
});

echo "\n";

// Test 2: Failed calls to trip the circuit
echo "Test 2: Failing calls to trip circuit\n";
try {
    $breaker->call(function () {
        throw new \Exception('Service unavailable');
    });
} catch (\Exception $e) {
    echo "Caught exception (expected)\n";
}

echo "\n";

try {
    $breaker->call(function () {
        throw new \Exception('Service still unavailable');
    });
} catch (\Exception $e) {
    echo "Caught exception (expected)\n";
}

echo "\n";

// Test 3: Call when circuit is open
echo "Test 3: Attempting call with open circuit\n";
try {
    $breaker->call(function () {
        return 'This will not execute';
    });
} catch (\Exception $e) {
    echo "Circuit is open - failing fast (expected)\n";
}

echo "\n";

// Wait for timeout
echo "Waiting for circuit timeout...\n";
sleep($breaker->getTimeout() + 1);

// Test 4: Recovery
echo "Test 4: Circuit recovery after timeout\n";
$breaker->call(function () {
    return ['status' => 'ok', 'recovered' => true];
});

echo "\n--- Final Statistics ---\n";
$stats = $metricsSubscriber->getStats();
echo "Successes: {$stats['successes']}\n";
echo "Failures: {$stats['failures']}\n";
echo "Circuit Opens: {$stats['circuit_opens']}\n";
echo "Final Circuit State: {$breaker->getState()}\n";

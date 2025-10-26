<?php

declare(strict_types=1);

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Contracts\MetricsCollectorInterface;
use Farzai\Breaker\Events\CallFailedEvent;
use Farzai\Breaker\Events\CallSucceededEvent;
use Farzai\Breaker\Events\CircuitClosedEvent;
use Farzai\Breaker\Events\CircuitHalfOpenedEvent;
use Farzai\Breaker\Events\CircuitOpenedEvent;
use Farzai\Breaker\Events\EventSubscriberInterface;
use Farzai\Breaker\Events\FallbackExecutedEvent;
use Farzai\Breaker\Events\Subscribers\MetricsSubscriber;
use Farzai\Breaker\Metrics\CircuitMetrics;

describe('MetricsSubscriber', function () {
    it('implements EventSubscriberInterface', function () {
        $collector = new class implements MetricsCollectorInterface
        {
            public function recordSuccess(int $timestamp): void {}

            public function recordFailure(int $timestamp): void {}

            public function recordRejection(): void {}

            public function recordFallback(): void {}

            public function recordStateTransition(int $timestamp): void {}

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $subscriber = new MetricsSubscriber($collector);

        expect($subscriber)->toBeInstanceOf(EventSubscriberInterface::class);
    });

    it('returns subscribed events with correct priorities', function () {
        $collector = new class implements MetricsCollectorInterface
        {
            public function recordSuccess(int $timestamp): void {}

            public function recordFailure(int $timestamp): void {}

            public function recordRejection(): void {}

            public function recordFallback(): void {}

            public function recordStateTransition(int $timestamp): void {}

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $events = MetricsSubscriber::getSubscribedEvents();

        expect($events)->toBeArray()
            ->toHaveKey(CallSucceededEvent::class)
            ->toHaveKey(CallFailedEvent::class)
            ->toHaveKey(FallbackExecutedEvent::class)
            ->toHaveKey(CircuitOpenedEvent::class)
            ->toHaveKey(CircuitClosedEvent::class)
            ->toHaveKey(CircuitHalfOpenedEvent::class);

        // Verify all priorities are 100
        expect($events[CallSucceededEvent::class]['priority'])->toBe(100)
            ->and($events[CallFailedEvent::class]['priority'])->toBe(100)
            ->and($events[FallbackExecutedEvent::class]['priority'])->toBe(100)
            ->and($events[CircuitOpenedEvent::class]['priority'])->toBe(100)
            ->and($events[CircuitClosedEvent::class]['priority'])->toBe(100)
            ->and($events[CircuitHalfOpenedEvent::class]['priority'])->toBe(100);
    });

    it('maps events to correct handler methods', function () {
        $events = MetricsSubscriber::getSubscribedEvents();

        expect($events[CallSucceededEvent::class]['method'])->toBe('onCallSucceeded')
            ->and($events[CallFailedEvent::class]['method'])->toBe('onCallFailed')
            ->and($events[FallbackExecutedEvent::class]['method'])->toBe('onFallbackExecuted')
            ->and($events[CircuitOpenedEvent::class]['method'])->toBe('onCircuitStateChanged')
            ->and($events[CircuitClosedEvent::class]['method'])->toBe('onCircuitStateChanged')
            ->and($events[CircuitHalfOpenedEvent::class]['method'])->toBe('onCircuitStateChanged');
    });

    it('records success metrics on CallSucceededEvent', function () {
        $successCalled = false;
        $recordedTimestamp = null;

        $collector = new class($successCalled, $recordedTimestamp) implements MetricsCollectorInterface
        {
            public function __construct(private &$called, private &$timestamp) {}

            public function recordSuccess(int $timestamp): void
            {
                $this->called = true;
                $this->timestamp = $timestamp;
            }

            public function recordFailure(int $timestamp): void {}

            public function recordRejection(): void {}

            public function recordFallback(): void {}

            public function recordStateTransition(int $timestamp): void {}

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $subscriber = new MetricsSubscriber($collector);
        $breaker = new CircuitBreaker('test-service');
        $breaker->addSubscriber($subscriber);

        $breaker->call(fn () => 'success');

        expect($successCalled)->toBeTrue()
            ->and($recordedTimestamp)->toBeInt()
            ->and($recordedTimestamp)->toBeGreaterThan(0);
    });

    it('records failure metrics on CallFailedEvent', function () {
        $failureCalled = false;
        $recordedTimestamp = null;

        $collector = new class($failureCalled, $recordedTimestamp) implements MetricsCollectorInterface
        {
            public function __construct(private &$called, private &$timestamp) {}

            public function recordSuccess(int $timestamp): void {}

            public function recordFailure(int $timestamp): void
            {
                $this->called = true;
                $this->timestamp = $timestamp;
            }

            public function recordRejection(): void {}

            public function recordFallback(): void {}

            public function recordStateTransition(int $timestamp): void {}

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $subscriber = new MetricsSubscriber($collector);
        $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 10]);
        $breaker->addSubscriber($subscriber);

        try {
            $breaker->call(fn () => throw new \Exception('Test failure'));
        } catch (\Exception $e) {
            // Expected
        }

        expect($failureCalled)->toBeTrue()
            ->and($recordedTimestamp)->toBeInt()
            ->and($recordedTimestamp)->toBeGreaterThan(0);
    });

    it('records fallback metrics on FallbackExecutedEvent', function () {
        $fallbackCalled = false;

        $collector = new class($fallbackCalled) implements MetricsCollectorInterface
        {
            public function __construct(private &$called) {}

            public function recordSuccess(int $timestamp): void {}

            public function recordFailure(int $timestamp): void {}

            public function recordRejection(): void {}

            public function recordFallback(): void
            {
                $this->called = true;
            }

            public function recordStateTransition(int $timestamp): void {}

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $subscriber = new MetricsSubscriber($collector);
        $breaker = new CircuitBreaker('test-service');
        $breaker->addSubscriber($subscriber);

        $breaker->callWithFallback(
            fn () => throw new \Exception('Primary failed'),
            fn () => 'fallback-result'
        );

        expect($fallbackCalled)->toBeTrue();
    });

    it('records state transition on CircuitOpenedEvent', function () {
        $transitionCalled = false;
        $recordedTimestamp = null;

        $collector = new class($transitionCalled, $recordedTimestamp) implements MetricsCollectorInterface
        {
            public function __construct(private &$called, private &$timestamp) {}

            public function recordSuccess(int $timestamp): void {}

            public function recordFailure(int $timestamp): void {}

            public function recordRejection(): void {}

            public function recordFallback(): void {}

            public function recordStateTransition(int $timestamp): void
            {
                $this->called = true;
                $this->timestamp = $timestamp;
            }

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $subscriber = new MetricsSubscriber($collector);
        $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 1]);
        $breaker->addSubscriber($subscriber);

        // Trip the circuit to trigger CircuitOpenedEvent
        try {
            $breaker->call(fn () => throw new \Exception('Error'));
        } catch (\Exception $e) {
            // Expected
        }

        expect($transitionCalled)->toBeTrue()
            ->and($recordedTimestamp)->toBeInt()
            ->and($recordedTimestamp)->toBeGreaterThan(0);
    });

    it('records state transition on CircuitHalfOpenedEvent', function () {
        $transitionCalled = false;

        $collector = new class($transitionCalled) implements MetricsCollectorInterface
        {
            public function __construct(private &$called) {}

            public function recordSuccess(int $timestamp): void {}

            public function recordFailure(int $timestamp): void {}

            public function recordRejection(): void {}

            public function recordFallback(): void {}

            public function recordStateTransition(int $timestamp): void
            {
                $this->called = true;
            }

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $subscriber = new MetricsSubscriber($collector);
        $breaker = new CircuitBreaker('test-service', [
            'failure_threshold' => 1,
            'timeout' => 0,
            'success_threshold' => 2,
        ]);
        $breaker->addSubscriber($subscriber);

        // Trip the circuit
        try {
            $breaker->call(fn () => throw new \Exception('Error'));
        } catch (\Exception $e) {
        }

        $transitionCalled = false; // Reset to specifically test half-open event

        // Wait for timeout
        sleep(1);

        // This should trigger half-open state
        try {
            $breaker->call(fn () => 'test');
        } catch (\Exception $e) {
        }

        expect($transitionCalled)->toBeTrue();
    });

    it('records state transition on CircuitClosedEvent', function () {
        $transitionCalled = false;

        $collector = new class($transitionCalled) implements MetricsCollectorInterface
        {
            public function __construct(private &$called) {}

            public function recordSuccess(int $timestamp): void {}

            public function recordFailure(int $timestamp): void {}

            public function recordRejection(): void {}

            public function recordFallback(): void {}

            public function recordStateTransition(int $timestamp): void
            {
                $this->called = true;
            }

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $subscriber = new MetricsSubscriber($collector);
        $breaker = new CircuitBreaker('test-service', [
            'failure_threshold' => 1,
            'timeout' => 0,
            'success_threshold' => 1,
        ]);
        $breaker->addSubscriber($subscriber);

        // Trip the circuit
        try {
            $breaker->call(fn () => throw new \Exception('Error'));
        } catch (\Exception $e) {
        }

        $transitionCalled = false; // Reset to specifically test closed event

        // Wait for timeout
        sleep(1);

        // Recover with successful call (triggers half-open, then closed)
        $breaker->call(fn () => 'success');

        expect($transitionCalled)->toBeTrue();
    });

    it('collects all metrics during circuit lifecycle', function () {
        $successCount = 0;
        $failureCount = 0;
        $fallbackCount = 0;
        $stateTransitionCount = 0;

        $collector = new class($successCount, $failureCount, $fallbackCount, $stateTransitionCount) implements MetricsCollectorInterface
        {
            public function __construct(
                private &$success,
                private &$failure,
                private &$fallback,
                private &$transition
            ) {}

            public function recordSuccess(int $timestamp): void
            {
                $this->success++;
            }

            public function recordFailure(int $timestamp): void
            {
                $this->failure++;
            }

            public function recordRejection(): void {}

            public function recordFallback(): void
            {
                $this->fallback++;
            }

            public function recordStateTransition(int $timestamp): void
            {
                $this->transition++;
            }

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $subscriber = new MetricsSubscriber($collector);
        $breaker = new CircuitBreaker('test-service', [
            'failure_threshold' => 2,
            'timeout' => 0,
            'success_threshold' => 1,
        ]);
        $breaker->addSubscriber($subscriber);

        // Successful call
        $breaker->call(fn () => 'success');

        // Failed call
        try {
            $breaker->call(fn () => throw new \Exception('Error 1'));
        } catch (\Exception $e) {
        }

        // Fallback on failure (before circuit trips)
        $breaker->callWithFallback(
            fn () => throw new \Exception('Error with fallback'),
            fn () => 'fallback'
        );

        // Another failure (trips circuit)
        try {
            $breaker->call(fn () => throw new \Exception('Error 2'));
        } catch (\Exception $e) {
        }

        // Wait for timeout
        sleep(1);

        // Successful recovery
        $breaker->call(fn () => 'recovered');

        // Verify all metrics were collected during complete lifecycle
        expect($successCount)->toBeGreaterThanOrEqual(2) // At least: initial success + recovery
            ->and($failureCount)->toBeGreaterThanOrEqual(2) // At least 2 failures to trip the circuit
            ->and($fallbackCount)->toBe(1) // Exactly one fallback executed
            ->and($stateTransitionCount)->toBeGreaterThanOrEqual(2); // At least: open + closed
    });

    it('can be used with multiple circuit breakers', function () {
        $eventCounts = [];

        $collector = new class($eventCounts) implements MetricsCollectorInterface
        {
            public function __construct(private &$counts) {}

            public function recordSuccess(int $timestamp): void
            {
                $this->counts['success'] = ($this->counts['success'] ?? 0) + 1;
            }

            public function recordFailure(int $timestamp): void
            {
                $this->counts['failure'] = ($this->counts['failure'] ?? 0) + 1;
            }

            public function recordRejection(): void {}

            public function recordFallback(): void {}

            public function recordStateTransition(int $timestamp): void {}

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $subscriber = new MetricsSubscriber($collector);

        $breaker1 = new CircuitBreaker('service-1');
        $breaker1->addSubscriber($subscriber);

        $breaker2 = new CircuitBreaker('service-2');
        $breaker2->addSubscriber($subscriber);

        // Use both breakers
        $breaker1->call(fn () => 'success1');
        $breaker2->call(fn () => 'success2');

        try {
            $breaker1->call(fn () => throw new \Exception('Fail'));
        } catch (\Exception $e) {
        }

        expect($eventCounts['success'])->toBe(2)
            ->and($eventCounts['failure'])->toBe(1);
    });

    it('preserves timestamp from events', function () {
        $timestamps = [];

        $collector = new class($timestamps) implements MetricsCollectorInterface
        {
            public function __construct(private &$timestamps) {}

            public function recordSuccess(int $timestamp): void
            {
                $this->timestamps[] = $timestamp;
            }

            public function recordFailure(int $timestamp): void
            {
                $this->timestamps[] = $timestamp;
            }

            public function recordRejection(): void {}

            public function recordFallback(): void {}

            public function recordStateTransition(int $timestamp): void
            {
                $this->timestamps[] = $timestamp;
            }

            public function getMetrics(): CircuitMetrics
            {
                return new CircuitMetrics(0, 0, 0, 0, 0);
            }

            public function reset(): void {}
        };

        $subscriber = new MetricsSubscriber($collector);
        $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 1]);
        $breaker->addSubscriber($subscriber);

        $before = time();

        $breaker->call(fn () => 'success');

        try {
            $breaker->call(fn () => throw new \Exception('Error'));
        } catch (\Exception $e) {
        }

        $after = time();

        // Verify all timestamps are reasonable (between before and after)
        expect($timestamps)->toHaveCount(3) // success + failure + state transition
            ->and($timestamps[0])->toBeGreaterThanOrEqual($before)
            ->and($timestamps[0])->toBeLessThanOrEqual($after)
            ->and($timestamps[1])->toBeGreaterThanOrEqual($before)
            ->and($timestamps[1])->toBeLessThanOrEqual($after);
    });
});

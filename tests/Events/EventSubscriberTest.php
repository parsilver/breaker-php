<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Events\CallFailedEvent;
use Farzai\Breaker\Events\CallSucceededEvent;
use Farzai\Breaker\Events\CircuitOpenedEvent;
use Farzai\Breaker\Events\EventSubscriberInterface;
use Farzai\Breaker\Events\Subscribers\LoggingSubscriber;
use Psr\Log\AbstractLogger;

// Test subscriber pattern
test('event subscribers can listen to multiple events', function () {
    $breaker = new CircuitBreaker('test-service');

    $events = [];

    $subscriber = new class($events) implements EventSubscriberInterface
    {
        public function __construct(private array &$events) {}

        public static function getSubscribedEvents(): array
        {
            return [
                CallSucceededEvent::class => 'onSuccess',
                CallFailedEvent::class => 'onFailure',
                CircuitOpenedEvent::class => 'onOpen',
            ];
        }

        public function onSuccess(CallSucceededEvent $event): void
        {
            $this->events[] = 'success';
        }

        public function onFailure(CallFailedEvent $event): void
        {
            $this->events[] = 'failure';
        }

        public function onOpen(CircuitOpenedEvent $event): void
        {
            $this->events[] = 'open';
        }
    };

    $breaker->addSubscriber($subscriber);

    // Test success
    $breaker->call(fn () => 'ok');
    expect($events)->toContain('success');
});

test('event subscribers support priority', function () {
    $breaker = new CircuitBreaker('test-service');
    $executionOrder = [];

    $subscriber = new class($executionOrder) implements EventSubscriberInterface
    {
        public function __construct(private array &$order) {}

        public static function getSubscribedEvents(): array
        {
            return [
                CallSucceededEvent::class => [
                    'method' => 'onSuccess',
                    'priority' => 100,
                ],
            ];
        }

        public function onSuccess(CallSucceededEvent $event): void
        {
            $this->order[] = 'subscriber';
        }
    };

    $breaker->addSubscriber($subscriber);

    $breaker->onSuccess(function () use (&$executionOrder) {
        $executionOrder[] = 'regular';
    }, 0);

    $breaker->call(fn () => 'test');

    // Subscriber with priority 100 should execute before regular listener with priority 0
    expect($executionOrder)->toBe(['subscriber', 'regular']);
});

test('subscriber can handle multiple listeners for same event', function () {
    $breaker = new CircuitBreaker('test-service');
    $calls = [];

    $subscriber = new class($calls) implements EventSubscriberInterface
    {
        public function __construct(private array &$calls) {}

        public static function getSubscribedEvents(): array
        {
            return [
                CallSucceededEvent::class => [
                    ['method' => 'onSuccessFirst', 'priority' => 100],
                    ['method' => 'onSuccessSecond', 'priority' => 50],
                ],
            ];
        }

        public function onSuccessFirst(CallSucceededEvent $event): void
        {
            $this->calls[] = 'first';
        }

        public function onSuccessSecond(CallSucceededEvent $event): void
        {
            $this->calls[] = 'second';
        }
    };

    $breaker->addSubscriber($subscriber);
    $breaker->call(fn () => 'test');

    expect($calls)->toBe(['first', 'second']);
});

test('LoggingSubscriber logs all events correctly', function () {
    $logs = [];

    $logger = new class($logs) extends AbstractLogger
    {
        public function __construct(private array &$logs) {}

        public function log($level, $message, array $context = []): void
        {
            $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
        }
    };

    $subscriber = new LoggingSubscriber($logger);
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 1]);

    $breaker->addSubscriber($subscriber);

    // Trigger success
    $breaker->call(fn () => 'success');

    // Trigger failure and opening
    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }

    // Should have logged multiple events
    expect($logs)->not->toBeEmpty();
    expect(array_column($logs, 'message'))->toContain('Circuit breaker opened - service experiencing failures');
});

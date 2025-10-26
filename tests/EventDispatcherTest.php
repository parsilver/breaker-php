<?php

use Farzai\Breaker\Events\EventDispatcher;

// Test EventDispatcher functionality
test('event dispatcher can clear all listeners for a specific event', function () {
    $dispatcher = new EventDispatcher;

    // Add listeners for different events
    $event1 = 'event1';
    $event2 = 'event2';

    $dispatcher->addListener($event1, function () {
        return 'event1 listener 1';
    });

    $dispatcher->addListener($event1, function () {
        return 'event1 listener 2';
    });

    $dispatcher->addListener($event2, function () {
        return 'event2 listener';
    });

    // Verify listeners were added
    expect($dispatcher->getListenerCount($event1))->toBe(2);
    expect($dispatcher->getListenerCount($event2))->toBe(1);

    // Clear listeners for event1
    $dispatcher->clearListeners($event1);

    // Verify event1 listeners were cleared but event2 listener remains
    expect($dispatcher->getListenerCount($event1))->toBe(0);
    expect($dispatcher->getListenerCount($event2))->toBe(1);
});

test('event dispatcher can clear all listeners for all events', function () {
    $dispatcher = new EventDispatcher;

    // Add listeners for different events
    $event1 = 'event1';
    $event2 = 'event2';

    $dispatcher->addListener($event1, function () {
        return 'event1 listener';
    });

    $dispatcher->addListener($event2, function () {
        return 'event2 listener';
    });

    // Verify listeners were added
    expect($dispatcher->getListenerCount($event1))->toBe(1);
    expect($dispatcher->getListenerCount($event2))->toBe(1);

    // Clear all listeners
    $dispatcher->clearListeners();

    // Verify all listeners were cleared
    expect($dispatcher->getListenerCount($event1))->toBe(0);
    expect($dispatcher->getListenerCount($event2))->toBe(0);
});

test('event dispatcher clear listeners does nothing for non-existent event', function () {
    $dispatcher = new EventDispatcher;

    // Add a listener for an event
    $event = 'event';
    $dispatcher->addListener($event, function () {
        return 'event listener';
    });

    // Verify listener was added
    expect($dispatcher->getListenerCount($event))->toBe(1);

    // Try to clear listeners for a non-existent event
    $dispatcher->clearListeners('non-existent-event');

    // Verify the existing listener is still there
    expect($dispatcher->getListenerCount($event))->toBe(1);
});

test('event dispatcher hasListeners returns correct values', function () {
    $dispatcher = new EventDispatcher;

    $event = 'test-event';

    // Initially no listeners
    expect($dispatcher->hasListeners($event))->toBeFalse();

    // Add a listener
    $dispatcher->addListener($event, function () {
        return 'test';
    });

    // Now should have listeners
    expect($dispatcher->hasListeners($event))->toBeTrue();

    // Clear the listeners
    $dispatcher->clearListeners($event);

    // Now should not have listeners again
    expect($dispatcher->hasListeners($event))->toBeFalse();
});

test('event dispatcher getListenerCount returns zero for non-existent event', function () {
    $dispatcher = new EventDispatcher;

    // Check count for a non-existent event
    expect($dispatcher->getListenerCount('non-existent-event'))->toBe(0);
});

test('event dispatcher dispatch does nothing for non-existent event', function () {
    $dispatcher = new EventDispatcher;

    // No listeners registered for this event
    $event = new class
    {
        public string $data = 'test';
    };

    // This should not throw any errors
    $dispatcher->dispatch($event);

    // If we get here without errors, the test passes
    expect(true)->toBeTrue();
});

// Error Handling Strategy Tests
test('event dispatcher defaults to SILENT error strategy', function () {
    $dispatcher = new EventDispatcher;

    expect($dispatcher->getErrorHandlingStrategy())
        ->toBe(EventDispatcher::ERROR_STRATEGY_SILENT);
});

test('event dispatcher can set error handling strategy to COLLECT', function () {
    $dispatcher = new EventDispatcher;

    $dispatcher->setErrorHandlingStrategy(EventDispatcher::ERROR_STRATEGY_COLLECT);

    expect($dispatcher->getErrorHandlingStrategy())
        ->toBe(EventDispatcher::ERROR_STRATEGY_COLLECT);
});

test('event dispatcher can set error handling strategy to STOP', function () {
    $dispatcher = new EventDispatcher;

    $dispatcher->setErrorHandlingStrategy(EventDispatcher::ERROR_STRATEGY_STOP);

    expect($dispatcher->getErrorHandlingStrategy())
        ->toBe(EventDispatcher::ERROR_STRATEGY_STOP);
});

test('event dispatcher throws exception for invalid error strategy', function () {
    $dispatcher = new EventDispatcher;

    expect(fn () => $dispatcher->setErrorHandlingStrategy('invalid'))
        ->toThrow(InvalidArgumentException::class);
});

test('event dispatcher SILENT strategy logs errors and continues', function () {
    $logger = new class extends \Psr\Log\NullLogger
    {
        public array $logs = [];

        public function error($message, array $context = []): void
        {
            $this->logs[] = ['message' => $message, 'context' => $context];
        }
    };

    $dispatcher = new EventDispatcher(logger: $logger);
    $dispatcher->setErrorHandlingStrategy(EventDispatcher::ERROR_STRATEGY_SILENT);

    $event = new class
    {
        public string $data = 'test';
    };

    $callCount = 0;

    // First listener throws exception
    $dispatcher->addListener($event::class, function () {
        throw new Exception('First listener error');
    });

    // Second listener should still execute
    $dispatcher->addListener($event::class, function () use (&$callCount) {
        $callCount++;
    });

    $dispatcher->dispatch($event);

    // Both listeners were attempted
    expect($callCount)->toBe(1);
    expect($logger->logs)->toHaveCount(1);
    expect($logger->logs[0]['context']['message'])->toBe('First listener error');
});

test('event dispatcher COLLECT strategy collects errors', function () {
    $dispatcher = new EventDispatcher;
    $dispatcher->setErrorHandlingStrategy(EventDispatcher::ERROR_STRATEGY_COLLECT);

    $event = new class
    {
        public string $data = 'test';
    };

    $exception1 = new Exception('Error 1');
    $exception2 = new Exception('Error 2');

    $dispatcher->addListener($event::class, function () use ($exception1) {
        throw $exception1;
    });

    $dispatcher->addListener($event::class, function () use ($exception2) {
        throw $exception2;
    });

    $dispatcher->dispatch($event);

    $errors = $dispatcher->getDispatchErrors();

    expect($errors)->toHaveCount(2);
    expect($errors[0]['exception'])->toBe($exception1);
    expect($errors[1]['exception'])->toBe($exception2);
});

test('event dispatcher can clear dispatch errors', function () {
    $dispatcher = new EventDispatcher;
    $dispatcher->setErrorHandlingStrategy(EventDispatcher::ERROR_STRATEGY_COLLECT);

    $event = new class
    {
        public string $data = 'test';
    };

    $dispatcher->addListener($event::class, function () {
        throw new Exception('Test error');
    });

    $dispatcher->dispatch($event);

    expect($dispatcher->getDispatchErrors())->toHaveCount(1);

    $dispatcher->clearDispatchErrors();

    expect($dispatcher->getDispatchErrors())->toHaveCount(0);
});

test('event dispatcher STOP strategy stops on first error', function () {
    $dispatcher = new EventDispatcher;
    $dispatcher->setErrorHandlingStrategy(EventDispatcher::ERROR_STRATEGY_STOP);

    $event = new class
    {
        public string $data = 'test';
    };

    $callCount = 0;

    // First listener throws exception
    $dispatcher->addListener($event::class, function () {
        throw new Exception('First listener error');
    });

    // Second listener should NOT execute
    $dispatcher->addListener($event::class, function () use (&$callCount) {
        $callCount++;
    });

    $dispatcher->dispatch($event);

    // Second listener was not called
    expect($callCount)->toBe(0);
});

test('event dispatcher can set custom logger', function () {
    $customLogger = new \Psr\Log\NullLogger;
    $dispatcher = new EventDispatcher;

    $dispatcher->setLogger($customLogger);

    // Verify logger was set by triggering an error
    $event = new class {};

    $dispatcher->addListener($event::class, function () {
        throw new Exception('Test');
    });

    // Should not throw
    expect(fn () => $dispatcher->dispatch($event))
        ->not->toThrow(Exception::class);
});

test('event dispatcher can get listener provider', function () {
    $dispatcher = new EventDispatcher;

    $provider = $dispatcher->getListenerProvider();

    expect($provider)->toBeInstanceOf(\Farzai\Breaker\Events\ListenerProviderInterface::class);
});

test('event dispatcher collects error details in COLLECT mode', function () {
    $dispatcher = new EventDispatcher;
    $dispatcher->setErrorHandlingStrategy(EventDispatcher::ERROR_STRATEGY_COLLECT);

    $event = new class
    {
        public string $data = 'test';
    };

    $testException = new RuntimeException('Test exception');

    $testListener = function () use ($testException) {
        throw $testException;
    };

    $dispatcher->addListener($event::class, $testListener);

    $dispatcher->dispatch($event);

    $errors = $dispatcher->getDispatchErrors();

    expect($errors)->toHaveCount(1);
    expect($errors[0]['event'])->toBe($event);
    expect($errors[0]['listener'])->toBe($testListener);
    expect($errors[0]['exception'])->toBe($testException);
});

test('event dispatcher preserves errors across multiple dispatches in COLLECT mode', function () {
    $dispatcher = new EventDispatcher;
    $dispatcher->setErrorHandlingStrategy(EventDispatcher::ERROR_STRATEGY_COLLECT);

    $event1 = new class
    {
        public string $name = 'event1';
    };
    $event2 = new class
    {
        public string $name = 'event2';
    };

    $dispatcher->addListener($event1::class, function () {
        throw new Exception('Event 1 error');
    });

    $dispatcher->addListener($event2::class, function () {
        throw new Exception('Event 2 error');
    });

    $dispatcher->dispatch($event1);
    $dispatcher->dispatch($event2);

    $errors = $dispatcher->getDispatchErrors();

    // Should have errors from both dispatches
    expect($errors)->toHaveCount(2);
});

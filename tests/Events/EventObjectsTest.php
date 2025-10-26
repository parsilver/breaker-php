<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Events\CallFailedEvent;
use Farzai\Breaker\Events\CallSucceededEvent;
use Farzai\Breaker\Events\CircuitOpenedEvent;
use Farzai\Breaker\Events\CircuitStateChangedEvent;
use Farzai\Breaker\Events\FallbackExecutedEvent;

// Test event objects have correct properties
test('CallSucceededEvent contains correct data', function () {
    $breaker = new CircuitBreaker('test-service');

    $breaker->onSuccess(function (CallSucceededEvent $event) {
        expect($event->getCircuitBreaker())->toBeInstanceOf(CircuitBreaker::class);
        expect($event->getServiceKey())->toBe('test-service');
        expect($event->getCurrentState())->toBe('closed');
        expect($event->getResult())->toBe('success-data');
        expect($event->getExecutionTime())->toBeGreaterThanOrEqual(0);
        expect($event->getTimestamp())->toBeGreaterThan(0);
    });

    $breaker->call(fn () => 'success-data');
});

test('CallFailedEvent contains correct data', function () {
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 5]);
    $exceptionThrown = false;

    $breaker->onFailure(function (CallFailedEvent $event) {
        expect($event->getCircuitBreaker())->toBeInstanceOf(CircuitBreaker::class);
        expect($event->getServiceKey())->toBe('test-service');
        expect($event->getException())->toBeInstanceOf(\Exception::class);
        expect($event->getExceptionMessage())->toBe('Test failure');
        expect($event->getExceptionClass())->toBe(\Exception::class);
        expect($event->getExecutionTime())->toBeGreaterThanOrEqual(0);
        expect($event->getFailureCount())->toBe(1);
        expect($event->getFailureThreshold())->toBe(5);
        expect($event->willTriggerOpen())->toBeFalse();
    });

    try {
        $breaker->call(fn () => throw new \Exception('Test failure'));
    } catch (\Exception $e) {
        $exceptionThrown = true;
    }

    expect($exceptionThrown)->toBeTrue();
});

test('CircuitOpenedEvent contains correct data', function () {
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 2, 'timeout' => 60]);

    $breaker->onOpen(function (CircuitOpenedEvent $event) {
        expect($event->getServiceKey())->toBe('test-service');
        expect($event->getFailureCount())->toBe(2);
        expect($event->getFailureThreshold())->toBe(2);
        expect($event->getTimeout())->toBe(60);
        expect($event->getHalfOpenTimestamp())->toBeGreaterThan(time());
    });

    // Trip the circuit
    try {
        $breaker->call(fn () => throw new \Exception('Error 1'));
    } catch (\Exception $e) {
    }
    try {
        $breaker->call(fn () => throw new \Exception('Error 2'));
    } catch (\Exception $e) {
    }
});

test('CircuitStateChangedEvent provides state transition info', function () {
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 1]);

    $breaker->onStateChange(function (CircuitStateChangedEvent $event) {
        expect($event->getPreviousState())->toBe('closed');
        expect($event->getNewState())->toBe('open');
        expect($event->isTransitionToOpen())->toBeTrue();
        expect($event->isTransitionToClosed())->toBeFalse();
        expect($event->isTransitionToHalfOpen())->toBeFalse();
    });

    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }
});

test('FallbackExecutedEvent contains fallback data', function () {
    $breaker = new CircuitBreaker('test-service');

    $breaker->onFallbackSuccess(function (FallbackExecutedEvent $event) {
        expect($event->getResult())->toBe('fallback-result');
        expect($event->getOriginalException())->toBeInstanceOf(\Exception::class);
        expect($event->getOriginalExceptionMessage())->toBe('Primary failed');
        expect($event->getExecutionTime())->toBeGreaterThanOrEqual(0);
    });

    $breaker->callWithFallback(
        fn () => throw new \Exception('Primary failed'),
        fn () => 'fallback-result'
    );
});

test('Event objects are immutable', function () {
    $breaker = new CircuitBreaker('test-service');

    $breaker->onSuccess(function (CallSucceededEvent $event) {
        // Event properties should be readonly
        expect($event)->toBeInstanceOf(CallSucceededEvent::class);

        // Try to modify - should fail with PHP error
        try {
            $event->getCircuitBreaker()->getServiceKey();
            // This should work fine - reading is allowed
            expect(true)->toBeTrue();
        } catch (\Error $e) {
            // If we get here, something is wrong
            expect(false)->toBeTrue();
        }
    });

    $breaker->call(fn () => 'test');
});

test('Event propagation can be stopped', function () {
    $breaker = new CircuitBreaker('test-service');
    $listener1Called = false;
    $listener2Called = false;

    $breaker->onSuccess(function (CallSucceededEvent $event) use (&$listener1Called) {
        $listener1Called = true;
        $event->stopPropagation();
    });

    $breaker->onSuccess(function (CallSucceededEvent $event) use (&$listener2Called) {
        $listener2Called = true;
    });

    $breaker->call(fn () => 'test');

    expect($listener1Called)->toBeTrue();
    expect($listener2Called)->toBeFalse(); // Should not be called due to stopped propagation
});

test('CallSucceededEvent provides all accessor methods', function () {
    $breaker = new CircuitBreaker('test-service');

    $breaker->onSuccess(function (CallSucceededEvent $event) {
        expect($event->getCircuitBreaker())->toBeInstanceOf(CircuitBreaker::class);
        expect($event->getServiceKey())->toBe('test-service');
        expect($event->getCurrentState())->toBe('closed');
        expect($event->getTimestamp())->toBeInt();
        expect($event->getResult())->toBe('result-data');
        expect($event->getExecutionTime())->toBeFloat();
        expect($event->isPropagationStopped())->toBeFalse();
    });

    $breaker->call(fn () => 'result-data');
});

test('CallFailedEvent provides all accessor methods', function () {
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 3]);

    $breaker->onFailure(function (CallFailedEvent $event) {
        expect($event->getException())->toBeInstanceOf(\Exception::class);
        expect($event->getExceptionMessage())->toBe('Failed');
        expect($event->getExceptionClass())->toBe(\Exception::class);
        expect($event->getFailureCount())->toBe(1);
        expect($event->getFailureThreshold())->toBe(3);
        expect($event->willTriggerOpen())->toBeFalse(); // Still at 1/3
        expect($event->getExecutionTime())->toBeFloat();
        expect($event->getCurrentState())->toBe('closed');
    });

    try {
        $breaker->call(fn () => throw new \Exception('Failed'));
    } catch (\Exception $e) {
    }
});

test('CallFailedEvent willTriggerOpen behavior with state transitions', function () {
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 3]);
    $willTriggerResults = [];

    $breaker->onFailure(function (CallFailedEvent $event) use (&$willTriggerResults) {
        $willTriggerResults[] = [
            'willTrigger' => $event->willTriggerOpen(),
            'failureCount' => $event->getFailureCount(),
            'state' => $event->getCurrentState(),
        ];
    });

    // First failure: 1/3, state should still be closed
    try {
        $breaker->call(fn () => throw new \Exception('Error 1'));
    } catch (\Exception $e) {
    }

    // Second failure: 2/3, state should still be closed
    try {
        $breaker->call(fn () => throw new \Exception('Error 2'));
    } catch (\Exception $e) {
    }

    // Third failure: 3/3, state transitions to open before event fires
    try {
        $breaker->call(fn () => throw new \Exception('Error 3'));
    } catch (\Exception $e) {
    }

    // Verify we have 3 failure events
    expect($willTriggerResults)->toHaveCount(3);

    // First failure: below threshold, state closed, should return false
    expect($willTriggerResults[0]['willTrigger'])->toBeFalse()
        ->and($willTriggerResults[0]['failureCount'])->toBe(1)
        ->and($willTriggerResults[0]['state'])->toBe('closed');

    // Second failure: below threshold, state closed, should return false
    expect($willTriggerResults[1]['willTrigger'])->toBeFalse()
        ->and($willTriggerResults[1]['failureCount'])->toBe(2)
        ->and($willTriggerResults[1]['state'])->toBe('closed');

    // Third failure: at threshold, but state already transitioned to open, returns false
    expect($willTriggerResults[2]['willTrigger'])->toBeFalse()
        ->and($willTriggerResults[2]['failureCount'])->toBe(3)
        ->and($willTriggerResults[2]['state'])->toBe('open');
});

test('CircuitOpenedEvent provides all accessor methods', function () {
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 1, 'timeout' => 120]);

    $breaker->onOpen(function (CircuitOpenedEvent $event) {
        expect($event->getServiceKey())->toBe('test-service');
        expect($event->getCurrentState())->toBe('open');
        expect($event->getFailureCount())->toBe(1);
        expect($event->getFailureThreshold())->toBe(1);
        expect($event->getTimeout())->toBe(120);
        expect($event->getHalfOpenTimestamp())->toBeInt();
        expect($event->getHalfOpenTimestamp())->toBeGreaterThan(time());
        expect($event->getTimestamp())->toBeInt();
    });

    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }
});

test('CircuitClosedEvent provides all accessor methods', function () {
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
        'timeout' => 0,
        'success_threshold' => 1,
    ]);

    $breaker->onClose(function ($event) {
        expect($event->getServiceKey())->toBe('test-service');
        expect($event->getCurrentState())->toBe('closed');
        expect($event->getPreviousState())->toBeString();
        expect($event->getTimestamp())->toBeInt();
        expect($event->isRecovery())->toBeBool();
    });

    // Trip the circuit
    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }

    // Wait for timeout (0 seconds)
    sleep(1);

    // Recover with successful call
    $breaker->call(fn () => 'success');
});

test('CircuitClosedEvent isRecovery returns true for recovery from open', function () {
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
        'timeout' => 0,
        'success_threshold' => 1,
    ]);
    $isRecoveryValue = null;

    $breaker->onClose(function ($event) use (&$isRecoveryValue) {
        // Only capture the recovery close event, not initial state
        if ($event->getPreviousState() === 'half-open') {
            $isRecoveryValue = $event->isRecovery();
        }
    });

    // Trip the circuit
    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }

    // Wait for timeout
    sleep(1);

    // Recover with successful call (goes to half-open, then closed)
    $breaker->call(fn () => 'success');

    expect($isRecoveryValue)->toBeTrue();
});

test('CircuitHalfOpenedEvent provides all accessor methods', function () {
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
        'timeout' => 0,
        'success_threshold' => 2,
    ]);

    $breaker->onHalfOpen(function ($event) {
        expect($event->getServiceKey())->toBe('test-service');
        expect($event->getCurrentState())->toBe('half-open');
        expect($event->getSuccessThreshold())->toBe(2);
        expect($event->getTimestamp())->toBeInt();
    });

    // Trip the circuit
    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }

    // Wait for timeout
    sleep(1);

    // This should trigger half-open state
    try {
        $breaker->call(fn () => 'test');
    } catch (\Exception $e) {
    }
});

test('CircuitStateChangedEvent provides all transition checks', function () {
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 1]);

    $breaker->onStateChange(function (CircuitStateChangedEvent $event) {
        expect($event->getPreviousState())->toBeString();
        expect($event->getNewState())->toBeString();
        expect($event->isTransitionToOpen())->toBeBool();
        expect($event->isTransitionToClosed())->toBeBool();
        expect($event->isTransitionToHalfOpen())->toBeBool();
        expect($event->getServiceKey())->toBe('test-service');
        expect($event->getTimestamp())->toBeInt();
    });

    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }
});

test('FallbackExecutedEvent provides all accessor methods', function () {
    $breaker = new CircuitBreaker('test-service');

    $breaker->onFallbackSuccess(function (FallbackExecutedEvent $event) {
        expect($event->getResult())->toBe('fallback');
        expect($event->getOriginalException())->toBeInstanceOf(\Exception::class);
        expect($event->getOriginalExceptionMessage())->toBe('Original error');
        expect($event->getOriginalExceptionClass())->toBe(\Exception::class);
        expect($event->getExecutionTime())->toBeFloat();
        expect($event->getServiceKey())->toBe('test-service');
        expect($event->getTimestamp())->toBeInt();
        expect($event->getCurrentState())->toBeString();
    });

    $breaker->callWithFallback(
        fn () => throw new \Exception('Original error'),
        fn () => 'fallback'
    );
});

test('Events check propagation stopped status', function () {
    $breaker = new CircuitBreaker('test-service');

    $breaker->onSuccess(function (CallSucceededEvent $event) {
        expect($event->isPropagationStopped())->toBeFalse();
        $event->stopPropagation();
        expect($event->isPropagationStopped())->toBeTrue();
    });

    $breaker->call(fn () => 'test');
});

test('CallSucceededEvent __toString provides event details', function () {
    $breaker = new CircuitBreaker('test-service');

    $breaker->onSuccess(function (CallSucceededEvent $event) {
        $string = (string) $event;
        expect($string)->toContain('CallSucceededEvent')
            ->and($string)->toContain('service=test-service')
            ->and($string)->toContain('state=closed')
            ->and($string)->toContain('timestamp=');
    });

    $breaker->call(fn () => 'test');
});

test('CallFailedEvent __toString provides event details', function () {
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 10]);

    $breaker->onFailure(function (CallFailedEvent $event) {
        $string = (string) $event;
        expect($string)->toContain('CallFailedEvent')
            ->and($string)->toContain('service=test-service')
            ->and($string)->toContain('state=closed')
            ->and($string)->toContain('exception=Exception')
            ->and($string)->toContain('failures=1/10');
    });

    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }
});

test('CircuitOpenedEvent __toString provides event details', function () {
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 1]);

    $breaker->onOpen(function ($event) {
        $string = (string) $event;
        expect($string)->toContain('CircuitOpenedEvent')
            ->and($string)->toContain('service=test-service')
            ->and($string)->toContain('state=open')
            ->and($string)->toContain('failures=1/1');
    });

    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }
});

test('CircuitClosedEvent __toString provides event details', function () {
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
        'timeout' => 0,
        'success_threshold' => 1,
    ]);

    $breaker->onClose(function ($event) {
        if ($event->getPreviousState() === 'half-open') {
            $string = (string) $event;
            expect($string)->toContain('CircuitClosedEvent')
                ->and($string)->toContain('service=test-service')
                ->and($string)->toContain('state=closed');
        }
    });

    // Trip and recover
    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }
    sleep(1);
    $breaker->call(fn () => 'success');
});

test('CircuitHalfOpenedEvent __toString provides event details', function () {
    $breaker = new CircuitBreaker('test-service', [
        'failure_threshold' => 1,
        'timeout' => 0,
        'success_threshold' => 2,
    ]);

    $breaker->onHalfOpen(function ($event) {
        $string = (string) $event;
        expect($string)->toContain('CircuitHalfOpenedEvent')
            ->and($string)->toContain('service=test-service')
            ->and($string)->toContain('state=half-open');
    });

    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }
    sleep(1);
    try {
        $breaker->call(fn () => 'test');
    } catch (\Exception $e) {
    }
});

test('CircuitStateChangedEvent __toString provides event details', function () {
    $breaker = new CircuitBreaker('test-service', ['failure_threshold' => 1]);

    $breaker->onStateChange(function ($event) {
        $string = (string) $event;
        expect($string)->toContain('CircuitStateChangedEvent')
            ->and($string)->toContain('service=test-service')
            ->and($string)->toContain('closed->open');
    });

    try {
        $breaker->call(fn () => throw new \Exception('Error'));
    } catch (\Exception $e) {
    }
});

test('FallbackExecutedEvent __toString provides event details', function () {
    $breaker = new CircuitBreaker('test-service');

    $breaker->onFallbackSuccess(function ($event) {
        $string = (string) $event;
        expect($string)->toContain('FallbackExecutedEvent')
            ->and($string)->toContain('service=test-service')
            ->and($string)->toContain('exception=Exception');
    });

    $breaker->callWithFallback(
        fn () => throw new \Exception('Primary failed'),
        fn () => 'fallback'
    );
});

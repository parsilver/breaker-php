<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Events\CallSucceededEvent;

// Test listener priority system
test('event listeners execute in priority order', function () {
    $breaker = new CircuitBreaker('test-service');
    $executionOrder = [];

    // Add listeners with different priorities (higher priority = earlier execution)
    $breaker->onSuccess(function () use (&$executionOrder) {
        $executionOrder[] = 'low';
    }, -10);

    $breaker->onSuccess(function () use (&$executionOrder) {
        $executionOrder[] = 'high';
    }, 100);

    $breaker->onSuccess(function () use (&$executionOrder) {
        $executionOrder[] = 'medium';
    }, 50);

    $breaker->onSuccess(function () use (&$executionOrder) {
        $executionOrder[] = 'default';
    }, 0);

    $breaker->call(fn () => 'test');

    // Should execute in priority order: high (100), medium (50), default (0), low (-10)
    expect($executionOrder)->toBe(['high', 'medium', 'default', 'low']);
});

test('listeners with same priority execute in registration order', function () {
    $breaker = new CircuitBreaker('test-service');
    $executionOrder = [];

    // All have same priority (default 0)
    $breaker->onSuccess(function () use (&$executionOrder) {
        $executionOrder[] = 'first';
    });

    $breaker->onSuccess(function () use (&$executionOrder) {
        $executionOrder[] = 'second';
    });

    $breaker->onSuccess(function () use (&$executionOrder) {
        $executionOrder[] = 'third';
    });

    $breaker->call(fn () => 'test');

    expect($executionOrder)->toBe(['first', 'second', 'third']);
});

test('high priority listener can stop propagation to lower priority listeners', function () {
    $breaker = new CircuitBreaker('test-service');
    $called = [
        'high' => false,
        'medium' => false,
        'low' => false,
    ];

    // High priority listener stops propagation
    $breaker->onSuccess(function (CallSucceededEvent $event) use (&$called) {
        $called['high'] = true;
        $event->stopPropagation();
    }, 100);

    $breaker->onSuccess(function () use (&$called) {
        $called['medium'] = true;
    }, 50);

    $breaker->onSuccess(function () use (&$called) {
        $called['low'] = true;
    }, -10);

    $breaker->call(fn () => 'test');

    expect($called['high'])->toBeTrue();
    expect($called['medium'])->toBeFalse();
    expect($called['low'])->toBeFalse();
});

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
    $event = 'non-existent-event';

    // This should not throw any errors
    $dispatcher->dispatch($event, ['arg1', 'arg2']);

    // If we get here without errors, the test passes
    expect(true)->toBeTrue();
});

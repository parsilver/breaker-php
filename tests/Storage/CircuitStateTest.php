<?php

use Farzai\Breaker\Storage\CircuitState;

describe('CircuitState', function () {
    test('it can create a circuit state with all properties', function () {
        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'open',
            failureCount: 5,
            successCount: 2,
            lastFailureTime: 1234567890
        );

        expect($state->serviceKey)->toBe('test-service');
        expect($state->state)->toBe('open');
        expect($state->failureCount)->toBe(5);
        expect($state->successCount)->toBe(2);
        expect($state->lastFailureTime)->toBe(1234567890);
    });

    test('it can create a circuit state with default values', function () {
        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed'
        );

        expect($state->serviceKey)->toBe('test-service');
        expect($state->state)->toBe('closed');
        expect($state->failureCount)->toBe(0);
        expect($state->successCount)->toBe(0);
        expect($state->lastFailureTime)->toBeNull();
    });

    test('it can create from array with full data', function () {
        $data = [
            'state' => 'half-open',
            'failure_count' => 3,
            'success_count' => 1,
            'last_failure_time' => 9876543210,
        ];

        $state = CircuitState::fromArray('my-service', $data);

        expect($state->serviceKey)->toBe('my-service');
        expect($state->state)->toBe('half-open');
        expect($state->failureCount)->toBe(3);
        expect($state->successCount)->toBe(1);
        expect($state->lastFailureTime)->toBe(9876543210);
    });

    test('it can create from array with missing keys', function () {
        $data = [
            'state' => 'open',
        ];

        $state = CircuitState::fromArray('another-service', $data);

        expect($state->serviceKey)->toBe('another-service');
        expect($state->state)->toBe('open');
        expect($state->failureCount)->toBe(0);
        expect($state->successCount)->toBe(0);
        expect($state->lastFailureTime)->toBeNull();
    });

    test('it can create from empty array with defaults', function () {
        $state = CircuitState::fromArray('default-service', []);

        expect($state->serviceKey)->toBe('default-service');
        expect($state->state)->toBe('closed');
        expect($state->failureCount)->toBe(0);
        expect($state->successCount)->toBe(0);
        expect($state->lastFailureTime)->toBeNull();
    });

    test('it handles zero last failure time as null', function () {
        $data = [
            'state' => 'closed',
            'last_failure_time' => 0,
        ];

        $state = CircuitState::fromArray('test-service', $data);

        expect($state->lastFailureTime)->toBeNull();
    });

    test('it handles negative last failure time as null', function () {
        $data = [
            'state' => 'closed',
            'last_failure_time' => -1,
        ];

        $state = CircuitState::fromArray('test-service', $data);

        expect($state->lastFailureTime)->toBeNull();
    });

    test('it can convert to array', function () {
        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'open',
            failureCount: 7,
            successCount: 3,
            lastFailureTime: 1111111111
        );

        $array = $state->toArray();

        expect($array)->toBe([
            'state' => 'open',
            'failure_count' => 7,
            'success_count' => 3,
            'last_failure_time' => 1111111111,
        ]);
    });

    test('it converts null last failure time to zero in array', function () {
        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed',
            failureCount: 0,
            successCount: 0,
            lastFailureTime: null
        );

        $array = $state->toArray();

        expect($array['last_failure_time'])->toBe(0);
    });

    test('it is immutable with withFailureCount', function () {
        $original = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed',
            failureCount: 5
        );

        $updated = $original->withFailureCount(10);

        // Original unchanged
        expect($original->failureCount)->toBe(5);

        // New instance has updated value
        expect($updated->failureCount)->toBe(10);

        // Other properties preserved
        expect($updated->serviceKey)->toBe('test-service');
        expect($updated->state)->toBe('closed');
    });

    test('it is immutable with withSuccessCount', function () {
        $original = new CircuitState(
            serviceKey: 'test-service',
            state: 'half-open',
            successCount: 2
        );

        $updated = $original->withSuccessCount(5);

        // Original unchanged
        expect($original->successCount)->toBe(2);

        // New instance has updated value
        expect($updated->successCount)->toBe(5);

        // Other properties preserved
        expect($updated->serviceKey)->toBe('test-service');
        expect($updated->state)->toBe('half-open');
    });

    test('it is immutable with withState', function () {
        $original = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed'
        );

        $updated = $original->withState('open');

        // Original unchanged
        expect($original->state)->toBe('closed');

        // New instance has updated value
        expect($updated->state)->toBe('open');

        // Other properties preserved
        expect($updated->serviceKey)->toBe('test-service');
    });

    test('it is immutable with withLastFailureTime', function () {
        $original = new CircuitState(
            serviceKey: 'test-service',
            state: 'open',
            lastFailureTime: 1000000000
        );

        $updated = $original->withLastFailureTime(2000000000);

        // Original unchanged
        expect($original->lastFailureTime)->toBe(1000000000);

        // New instance has updated value
        expect($updated->lastFailureTime)->toBe(2000000000);

        // Other properties preserved
        expect($updated->serviceKey)->toBe('test-service');
        expect($updated->state)->toBe('open');
    });

    test('it can set last failure time to null via with method', function () {
        $original = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed',
            lastFailureTime: 1234567890
        );

        $updated = $original->withLastFailureTime(null);

        expect($original->lastFailureTime)->toBe(1234567890);
        expect($updated->lastFailureTime)->toBeNull();
    });

    test('it is readonly and cannot be modified directly', function () {
        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed'
        );

        expect(function () use ($state) {
            $state->serviceKey = 'modified';
        })->toThrow(Error::class);
    });

    test('it preserves all properties through multiple with operations', function () {
        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed',
            failureCount: 5,
            successCount: 3,
            lastFailureTime: 1234567890
        );

        $updated = $state
            ->withState('half-open')
            ->withSuccessCount(10)
            ->withFailureCount(0);

        expect($updated->serviceKey)->toBe('test-service');
        expect($updated->state)->toBe('half-open');
        expect($updated->failureCount)->toBe(0);
        expect($updated->successCount)->toBe(10);
        expect($updated->lastFailureTime)->toBe(1234567890);

        // Original still unchanged
        expect($state->state)->toBe('closed');
        expect($state->failureCount)->toBe(5);
        expect($state->successCount)->toBe(3);
    });

    test('it handles type coercion in fromArray', function () {
        $data = [
            'state' => 123, // Will be cast to string
            'failure_count' => '5', // Will be cast to int
            'success_count' => '2', // Will be cast to int
            'last_failure_time' => '9999999', // Will be cast to int
        ];

        $state = CircuitState::fromArray('test-service', $data);

        expect($state->state)->toBe('123');
        expect($state->failureCount)->toBe(5);
        expect($state->successCount)->toBe(2);
        expect($state->lastFailureTime)->toBe(9999999);
    });

    test('it can roundtrip through array serialization', function () {
        $original = new CircuitState(
            serviceKey: 'test-service',
            state: 'half-open',
            failureCount: 8,
            successCount: 4,
            lastFailureTime: 1234567890
        );

        $array = $original->toArray();
        $restored = CircuitState::fromArray('test-service', $array);

        expect($restored->serviceKey)->toBe($original->serviceKey);
        expect($restored->state)->toBe($original->state);
        expect($restored->failureCount)->toBe($original->failureCount);
        expect($restored->successCount)->toBe($original->successCount);
        expect($restored->lastFailureTime)->toBe($original->lastFailureTime);
    });
});

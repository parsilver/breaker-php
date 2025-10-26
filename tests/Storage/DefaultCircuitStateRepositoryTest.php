<?php

use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\CircuitState;
use Farzai\Breaker\Storage\DefaultCircuitStateRepository;
use Farzai\Breaker\Storage\JsonStorageSerializer;

describe('DefaultCircuitStateRepository', function () {
    test('it can create a repository', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;

        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        expect($repository)->toBeInstanceOf(DefaultCircuitStateRepository::class);
    });

    test('it returns null when state does not exist', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $state = $repository->find('non-existent-service');

        expect($state)->toBeNull();
    });

    test('it can save and find a circuit state', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'open',
            failureCount: 5,
            successCount: 2,
            lastFailureTime: 1234567890
        );

        $repository->save($state);

        $retrieved = $repository->find('test-service');

        expect($retrieved)->toBeInstanceOf(CircuitState::class);
        expect($retrieved->serviceKey)->toBe('test-service');
        expect($retrieved->state)->toBe('open');
        expect($retrieved->failureCount)->toBe(5);
        expect($retrieved->successCount)->toBe(2);
        expect($retrieved->lastFailureTime)->toBe(1234567890);
    });

    test('it can update existing state', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        // Save initial state
        $state1 = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed',
            failureCount: 0
        );
        $repository->save($state1);

        // Update state
        $state2 = new CircuitState(
            serviceKey: 'test-service',
            state: 'open',
            failureCount: 10
        );
        $repository->save($state2);

        $retrieved = $repository->find('test-service');

        expect($retrieved->state)->toBe('open');
        expect($retrieved->failureCount)->toBe(10);
    });

    test('it can delete a circuit state', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed'
        );

        $repository->save($state);
        expect($repository->find('test-service'))->not->toBeNull();

        $repository->delete('test-service');
        expect($repository->find('test-service'))->toBeNull();
    });

    test('it can check if state exists', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        expect($repository->exists('test-service'))->toBeFalse();

        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed'
        );
        $repository->save($state);

        expect($repository->exists('test-service'))->toBeTrue();

        $repository->delete('test-service');

        expect($repository->exists('test-service'))->toBeFalse();
    });

    test('it generates consistent storage keys for same service', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $state = new CircuitState(
            serviceKey: 'my-service',
            state: 'closed'
        );

        $repository->save($state);
        $repository->save($state); // Save again

        // Should be able to retrieve - proves same key is used
        $retrieved = $repository->find('my-service');
        expect($retrieved)->not->toBeNull();
    });

    test('it uses SHA-256 hashing for storage keys', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed'
        );

        $repository->save($state);

        // Calculate expected key
        $expectedKey = 'cb_'.hash('sha256', 'test-service');

        // Verify adapter has data at the expected key
        $rawData = $adapter->read($expectedKey);
        expect($rawData)->not->toBeNull();
    });

    test('it handles special characters in service key', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $serviceKey = 'service/with:special@characters#!';

        $state = new CircuitState(
            serviceKey: $serviceKey,
            state: 'open',
            failureCount: 3
        );

        $repository->save($state);
        $retrieved = $repository->find($serviceKey);

        expect($retrieved)->not->toBeNull();
        expect($retrieved->serviceKey)->toBe($serviceKey);
    });

    test('it maintains separation between different services', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $state1 = new CircuitState(
            serviceKey: 'service-1',
            state: 'open',
            failureCount: 5
        );

        $state2 = new CircuitState(
            serviceKey: 'service-2',
            state: 'closed',
            failureCount: 0
        );

        $repository->save($state1);
        $repository->save($state2);

        $retrieved1 = $repository->find('service-1');
        $retrieved2 = $repository->find('service-2');

        expect($retrieved1->state)->toBe('open');
        expect($retrieved1->failureCount)->toBe(5);

        expect($retrieved2->state)->toBe('closed');
        expect($retrieved2->failureCount)->toBe(0);
    });

    test('it handles null last failure time correctly', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $state = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed',
            lastFailureTime: null
        );

        $repository->save($state);
        $retrieved = $repository->find('test-service');

        expect($retrieved->lastFailureTime)->toBeNull();
    });

    test('it preserves all state properties through save and find', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $state = new CircuitState(
            serviceKey: 'complete-service',
            state: 'half-open',
            failureCount: 7,
            successCount: 4,
            lastFailureTime: 1234567890
        );

        $repository->save($state);
        $retrieved = $repository->find('complete-service');

        expect($retrieved->serviceKey)->toBe($state->serviceKey);
        expect($retrieved->state)->toBe($state->state);
        expect($retrieved->failureCount)->toBe($state->failureCount);
        expect($retrieved->successCount)->toBe($state->successCount);
        expect($retrieved->lastFailureTime)->toBe($state->lastFailureTime);
    });

    test('it delete non-existent service does not throw', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        // Should not throw
        expect(fn () => $repository->delete('non-existent'))
            ->not->toThrow(Exception::class);
    });

    test('it uses cb_ prefix for storage keys', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $state = new CircuitState(
            serviceKey: 'test',
            state: 'closed'
        );

        $repository->save($state);

        // Get all keys from adapter
        $reflection = new ReflectionClass($adapter);
        $property = $reflection->getProperty('storage');
        $property->setAccessible(true);
        $storage = $property->getValue($adapter);

        // All keys should start with 'cb_'
        foreach (array_keys($storage) as $key) {
            expect($key)->toStartWith('cb_');
        }
    });

    test('it handles concurrent saves to same service', function () {
        $adapter = new InMemoryStorageAdapter;
        $serializer = new JsonStorageSerializer;
        $repository = new DefaultCircuitStateRepository($adapter, $serializer);

        $state1 = new CircuitState(
            serviceKey: 'test-service',
            state: 'closed',
            failureCount: 1
        );

        $state2 = new CircuitState(
            serviceKey: 'test-service',
            state: 'open',
            failureCount: 10
        );

        $repository->save($state1);
        $repository->save($state2);

        $retrieved = $repository->find('test-service');

        // Last save should win
        expect($retrieved->state)->toBe('open');
        expect($retrieved->failureCount)->toBe(10);
    });
});

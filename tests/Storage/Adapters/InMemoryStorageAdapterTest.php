<?php

use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;

describe('InMemoryStorageAdapter', function () {
    test('it can create an adapter', function () {
        $adapter = new InMemoryStorageAdapter;

        expect($adapter)->toBeInstanceOf(InMemoryStorageAdapter::class);
    });

    test('it returns null for non-existent key', function () {
        $adapter = new InMemoryStorageAdapter;

        $result = $adapter->read('non-existent');

        expect($result)->toBeNull();
    });

    test('it can write and read data', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('test-key', 'test-value');
        $result = $adapter->read('test-key');

        expect($result)->toBe('test-value');
    });

    test('it can overwrite existing data', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('key', 'value1');
        $adapter->write('key', 'value2');

        expect($adapter->read('key'))->toBe('value2');
    });

    test('it can check if key exists', function () {
        $adapter = new InMemoryStorageAdapter;

        expect($adapter->exists('key'))->toBeFalse();

        $adapter->write('key', 'value');

        expect($adapter->exists('key'))->toBeTrue();
    });

    test('it can delete a key', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('key', 'value');
        expect($adapter->exists('key'))->toBeTrue();

        $adapter->delete('key');

        expect($adapter->exists('key'))->toBeFalse();
        expect($adapter->read('key'))->toBeNull();
    });

    test('it can clear all data', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('key1', 'value1');
        $adapter->write('key2', 'value2');
        $adapter->write('key3', 'value3');

        $adapter->clear();

        expect($adapter->read('key1'))->toBeNull();
        expect($adapter->read('key2'))->toBeNull();
        expect($adapter->read('key3'))->toBeNull();
        expect($adapter->exists('key1'))->toBeFalse();
    });

    test('it returns correct name', function () {
        $adapter = new InMemoryStorageAdapter;

        expect($adapter->getName())->toBe('memory');
    });

    test('it handles TTL correctly', function () {
        $adapter = new InMemoryStorageAdapter;

        // Write with 2 second TTL
        $adapter->write('key', 'value', 2);

        // Should exist immediately
        expect($adapter->read('key'))->toBe('value');
        expect($adapter->exists('key'))->toBeTrue();

        // Wait for expiry
        sleep(3);

        // Should be expired now
        expect($adapter->read('key'))->toBeNull();
        expect($adapter->exists('key'))->toBeFalse();
    });

    test('it write without TTL does not expire', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('key', 'value'); // No TTL

        // Simulate time passing
        sleep(1);

        expect($adapter->read('key'))->toBe('value');
        expect($adapter->exists('key'))->toBeTrue();
    });

    test('it can cleanup expired items', function () {
        $adapter = new InMemoryStorageAdapter;

        // Write items with different TTLs
        $adapter->write('key1', 'value1', 1); // Expires in 1 second
        $adapter->write('key2', 'value2'); // No expiry
        $adapter->write('key3', 'value3', 10); // Expires in 10 seconds

        // Wait for key1 to expire
        sleep(2);

        // Cleanup
        $removed = $adapter->cleanupExpired();

        expect($removed)->toBe(1); // Only key1 should be removed
        expect($adapter->exists('key1'))->toBeFalse();
        expect($adapter->exists('key2'))->toBeTrue();
        expect($adapter->exists('key3'))->toBeTrue();
    });

    test('it cleanupExpired returns zero when no items expired', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('key1', 'value1'); // No TTL
        $adapter->write('key2', 'value2', 100); // Long TTL

        $removed = $adapter->cleanupExpired();

        expect($removed)->toBe(0);
    });

    test('it cleanupExpired works on empty storage', function () {
        $adapter = new InMemoryStorageAdapter;

        $removed = $adapter->cleanupExpired();

        expect($removed)->toBe(0);
    });

    test('it handles multiple keys independently', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('key1', 'value1');
        $adapter->write('key2', 'value2');
        $adapter->write('key3', 'value3');

        expect($adapter->read('key1'))->toBe('value1');
        expect($adapter->read('key2'))->toBe('value2');
        expect($adapter->read('key3'))->toBe('value3');

        $adapter->delete('key2');

        expect($adapter->read('key1'))->toBe('value1');
        expect($adapter->read('key2'))->toBeNull();
        expect($adapter->read('key3'))->toBe('value3');
    });

    test('it read removes expired item from storage', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('key', 'value', 1);

        sleep(2);

        // First read should return null and remove from storage
        expect($adapter->read('key'))->toBeNull();

        // Second read should still return null
        expect($adapter->read('key'))->toBeNull();
    });

    test('it exists removes expired item from storage', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('key', 'value', 1);

        sleep(2);

        // exists() should return false and remove from storage
        expect($adapter->exists('key'))->toBeFalse();

        // Subsequent exists() should still return false
        expect($adapter->exists('key'))->toBeFalse();
    });

    test('it delete non-existent key does not throw', function () {
        $adapter = new InMemoryStorageAdapter;

        // Should not throw
        expect(fn () => $adapter->delete('non-existent'))
            ->not->toThrow(Exception::class);
    });

    test('it handles empty string values', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('key', '');

        expect($adapter->read('key'))->toBe('');
        expect($adapter->exists('key'))->toBeTrue();
    });

    test('it handles special characters in keys', function () {
        $adapter = new InMemoryStorageAdapter;

        $key = 'key:with:special@characters#!';

        $adapter->write($key, 'value');

        expect($adapter->read($key))->toBe('value');
        expect($adapter->exists($key))->toBeTrue();
    });

    test('it handles special characters in values', function () {
        $adapter = new InMemoryStorageAdapter;

        $value = 'value with special chars: !@#$%^&*()';

        $adapter->write('key', $value);

        expect($adapter->read('key'))->toBe($value);
    });

    test('it handles large values', function () {
        $adapter = new InMemoryStorageAdapter;

        $largeValue = str_repeat('A', 10000);

        $adapter->write('key', $largeValue);

        expect($adapter->read('key'))->toBe($largeValue);
    });

    test('it handles many keys', function () {
        $adapter = new InMemoryStorageAdapter;

        // Write 100 keys
        for ($i = 0; $i < 100; $i++) {
            $adapter->write("key{$i}", "value{$i}");
        }

        // Verify all can be read
        for ($i = 0; $i < 100; $i++) {
            expect($adapter->read("key{$i}"))->toBe("value{$i}");
        }

        // Clear all
        $adapter->clear();

        // Verify all are gone
        for ($i = 0; $i < 100; $i++) {
            expect($adapter->read("key{$i}"))->toBeNull();
        }
    });

    test('it TTL of zero expires within one second', function () {
        $adapter = new InMemoryStorageAdapter;

        $adapter->write('key', 'value', 0);

        // With TTL of 0, it expires at time() + 0, so it will expire after time() passes
        // Wait a moment for time to pass
        sleep(1);

        // Should be expired now
        expect($adapter->read('key'))->toBeNull();
        expect($adapter->exists('key'))->toBeFalse();
    });

    test('it can update TTL by rewriting', function () {
        $adapter = new InMemoryStorageAdapter;

        // Write with short TTL
        $adapter->write('key', 'value1', 1);

        sleep(1);

        // Rewrite before first expires with longer TTL
        $adapter->write('key', 'value2', 10);

        // Should still exist
        expect($adapter->read('key'))->toBe('value2');
        expect($adapter->exists('key'))->toBeTrue();
    });

    test('it cleanupExpired removes all expired items', function () {
        $adapter = new InMemoryStorageAdapter;

        // Write multiple items that will expire
        $adapter->write('key1', 'value1', 1);
        $adapter->write('key2', 'value2', 1);
        $adapter->write('key3', 'value3', 1);
        $adapter->write('key4', 'value4'); // No expiry

        sleep(2);

        $removed = $adapter->cleanupExpired();

        expect($removed)->toBe(3);
        expect($adapter->exists('key1'))->toBeFalse();
        expect($adapter->exists('key2'))->toBeFalse();
        expect($adapter->exists('key3'))->toBeFalse();
        expect($adapter->exists('key4'))->toBeTrue();
    });

    test('it maintains data isolation between instances', function () {
        $adapter1 = new InMemoryStorageAdapter;
        $adapter2 = new InMemoryStorageAdapter;

        $adapter1->write('key', 'value1');
        $adapter2->write('key', 'value2');

        expect($adapter1->read('key'))->toBe('value1');
        expect($adapter2->read('key'))->toBe('value2');
    });
});

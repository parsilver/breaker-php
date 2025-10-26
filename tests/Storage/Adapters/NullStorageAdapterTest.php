<?php

declare(strict_types=1);

use Farzai\Breaker\Storage\Adapters\NullStorageAdapter;

describe('NullStorageAdapter', function () {
    it('can create a null adapter', function () {
        $adapter = new NullStorageAdapter;

        expect($adapter)->toBeInstanceOf(NullStorageAdapter::class);
    });

    it('returns null on read', function () {
        $adapter = new NullStorageAdapter;

        expect($adapter->read('any-key'))->toBeNull();
    });

    it('does nothing on write', function () {
        $adapter = new NullStorageAdapter;

        expect(fn () => $adapter->write('key', 'value'))->not->toThrow(Exception::class);
    });

    it('returns false on exists', function () {
        $adapter = new NullStorageAdapter;

        expect($adapter->exists('any-key'))->toBeFalse();
    });

    it('does nothing on delete', function () {
        $adapter = new NullStorageAdapter;

        expect(fn () => $adapter->delete('key'))->not->toThrow(Exception::class);
    });

    it('does nothing on clear', function () {
        $adapter = new NullStorageAdapter;

        expect(fn () => $adapter->clear())->not->toThrow(Exception::class);
    });

    it('returns correct name', function () {
        $adapter = new NullStorageAdapter;

        expect($adapter->getName())->toBe('null');
    });

    it('write with TTL does nothing', function () {
        $adapter = new NullStorageAdapter;

        expect(fn () => $adapter->write('key', 'value', 3600))->not->toThrow(Exception::class);
    });

    it('can perform multiple operations without side effects', function () {
        $adapter = new NullStorageAdapter;

        $adapter->write('key1', 'value1');
        $adapter->write('key2', 'value2');

        expect($adapter->read('key1'))->toBeNull()
            ->and($adapter->read('key2'))->toBeNull()
            ->and($adapter->exists('key1'))->toBeFalse()
            ->and($adapter->exists('key2'))->toBeFalse();
    });

    it('is safe to use in production as a no-op adapter', function () {
        $adapter = new NullStorageAdapter;

        // Simulate typical usage pattern
        $adapter->write('circuit:api-service', '{"state":"closed"}', 3600);
        $result = $adapter->read('circuit:api-service');
        expect($result)->toBeNull();

        $adapter->delete('circuit:api-service');
        $exists = $adapter->exists('circuit:api-service');
        expect($exists)->toBeFalse();

        $adapter->clear();
        // No exception thrown
        expect(true)->toBeTrue();
    });
});

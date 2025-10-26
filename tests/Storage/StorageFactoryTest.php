<?php

declare(strict_types=1);

use Farzai\Breaker\Storage\Adapters\FallbackStorageAdapter;
use Farzai\Breaker\Storage\Adapters\FileStorageAdapter;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\Adapters\NullStorageAdapter;
use Farzai\Breaker\Storage\Adapters\Psr16StorageAdapter;
use Farzai\Breaker\Storage\CircuitStateRepository;
use Farzai\Breaker\Storage\DefaultCircuitStateRepository;
use Farzai\Breaker\Storage\StorageBuilder;
use Farzai\Breaker\Storage\StorageFactory;
use Psr\SimpleCache\CacheInterface;

describe('StorageFactory', function () {
    it('can create file storage adapter', function () {
        $adapter = StorageFactory::file(__DIR__.'/test-storage');

        expect($adapter)->toBeInstanceOf(FileStorageAdapter::class);
    });

    it('can create memory storage adapter', function () {
        $adapter = StorageFactory::memory();

        expect($adapter)->toBeInstanceOf(InMemoryStorageAdapter::class);
    });

    it('can create null storage adapter', function () {
        $adapter = StorageFactory::null();

        expect($adapter)->toBeInstanceOf(NullStorageAdapter::class);
    });

    it('can create psr16 storage adapter', function () {
        $cache = new class implements CacheInterface
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return null;
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function clear(): bool
            {
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                return [];
            }

            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return false;
            }
        };

        $adapter = StorageFactory::psr16($cache);

        expect($adapter)->toBeInstanceOf(Psr16StorageAdapter::class);
    });

    it('can create psr16 storage adapter with TTL', function () {
        $cache = new class implements CacheInterface
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return null;
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function clear(): bool
            {
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                return [];
            }

            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return false;
            }
        };

        $adapter = StorageFactory::psr16($cache, 3600);

        expect($adapter)->toBeInstanceOf(Psr16StorageAdapter::class);
    });

    it('can create fallback storage adapter', function () {
        $adapters = [
            StorageFactory::memory(),
            StorageFactory::null(),
        ];

        $adapter = StorageFactory::fallback($adapters);

        expect($adapter)->toBeInstanceOf(FallbackStorageAdapter::class);
    });

    it('can create repository from adapter', function () {
        $adapter = StorageFactory::memory();
        $repository = StorageFactory::createRepository($adapter);

        expect($repository)->toBeInstanceOf(CircuitStateRepository::class)
            ->and($repository)->toBeInstanceOf(DefaultCircuitStateRepository::class);
    });

    it('can create builder with string type', function () {
        $builder = StorageFactory::builder('memory');

        expect($builder)->toBeInstanceOf(StorageBuilder::class);
    });

    it('can create builder with file type and config', function () {
        $builder = StorageFactory::builder('file', ['path' => __DIR__]);

        expect($builder)->toBeInstanceOf(StorageBuilder::class);
    });

    it('can create builder with null type', function () {
        $builder = StorageFactory::builder('null');

        expect($builder)->toBeInstanceOf(StorageBuilder::class);
    });

    it('can create builder with adapter instance', function () {
        $adapter = StorageFactory::memory();
        $builder = StorageFactory::builder($adapter);

        expect($builder)->toBeInstanceOf(StorageBuilder::class);
    });

    it('throws exception for unknown adapter type', function () {
        expect(fn () => StorageFactory::builder('unknown'))
            ->toThrow(InvalidArgumentException::class, 'Unknown adapter type');
    });

    it('throws exception for psr16 without cache', function () {
        expect(fn () => StorageFactory::builder('psr16'))
            ->toThrow(InvalidArgumentException::class, 'PSR-16 adapter requires cache instance');
    });

    it('can create psr16 builder with cache config', function () {
        $cache = new class implements CacheInterface
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return null;
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function clear(): bool
            {
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                return [];
            }

            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return false;
            }
        };

        $builder = StorageFactory::builder('psr16', ['cache' => $cache]);

        expect($builder)->toBeInstanceOf(StorageBuilder::class);
    });

    it('can create psr16 builder with cache and TTL', function () {
        $cache = new class implements CacheInterface
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return null;
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }

            public function delete(string $key): bool
            {
                return true;
            }

            public function clear(): bool
            {
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                return [];
            }

            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return false;
            }
        };

        $builder = StorageFactory::builder('psr16', ['cache' => $cache, 'ttl' => 7200]);

        expect($builder)->toBeInstanceOf(StorageBuilder::class);
    });

    it('uses system temp dir when no path provided for file adapter', function () {
        $builder = StorageFactory::builder('file');

        expect($builder)->toBeInstanceOf(StorageBuilder::class);
    });
});

<?php

declare(strict_types=1);

use Farzai\Breaker\Exceptions\StorageException;
use Farzai\Breaker\Storage\Adapters\FallbackStorageAdapter;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\Adapters\NullStorageAdapter;
use Farzai\Breaker\Storage\StorageAdapter;
use Psr\Log\NullLogger;

describe('FallbackStorageAdapter', function () {
    it('can create with multiple adapters', function () {
        $adapters = [
            new InMemoryStorageAdapter,
            new NullStorageAdapter,
        ];

        $adapter = new FallbackStorageAdapter($adapters);

        expect($adapter)->toBeInstanceOf(FallbackStorageAdapter::class);
    });

    it('throws exception when no adapters provided', function () {
        expect(fn () => new FallbackStorageAdapter([]))
            ->toThrow(InvalidArgumentException::class, 'At least one storage adapter is required');
    });

    it('returns correct name with adapter chain', function () {
        $adapters = [
            new InMemoryStorageAdapter,
            new NullStorageAdapter,
        ];

        $adapter = new FallbackStorageAdapter($adapters);

        expect($adapter->getName())->toBe('fallback(memory,null)');
    });

    it('reads from first successful adapter', function () {
        $adapter1 = new InMemoryStorageAdapter;
        $adapter1->write('key', 'value-from-adapter1');

        $adapter2 = new InMemoryStorageAdapter;
        $adapter2->write('key', 'value-from-adapter2');

        $fallback = new FallbackStorageAdapter([$adapter1, $adapter2]);

        expect($fallback->read('key'))->toBe('value-from-adapter1');
    });

    it('falls back to second adapter when first fails', function () {
        $failingAdapter = new class implements StorageAdapter
        {
            public function read(string $key): ?string
            {
                throw new Exception('Adapter 1 failed');
            }

            public function write(string $key, string $value, ?int $ttl = null): void {}

            public function exists(string $key): bool
            {
                return false;
            }

            public function delete(string $key): void {}

            public function clear(): void {}

            public function getName(): string
            {
                return 'failing';
            }
        };

        $workingAdapter = new InMemoryStorageAdapter;
        $workingAdapter->write('key', 'fallback-value');

        $fallback = new FallbackStorageAdapter([$failingAdapter, $workingAdapter]);

        expect($fallback->read('key'))->toBe('fallback-value');
    });

    it('throws exception when all adapters fail on read', function () {
        $failingAdapter1 = new class implements StorageAdapter
        {
            public function read(string $key): ?string
            {
                throw new Exception('Adapter 1 failed');
            }

            public function write(string $key, string $value, ?int $ttl = null): void {}

            public function exists(string $key): bool
            {
                return false;
            }

            public function delete(string $key): void {}

            public function clear(): void {}

            public function getName(): string
            {
                return 'failing1';
            }
        };

        $failingAdapter2 = new class implements StorageAdapter
        {
            public function read(string $key): ?string
            {
                throw new Exception('Adapter 2 failed');
            }

            public function write(string $key, string $value, ?int $ttl = null): void {}

            public function exists(string $key): bool
            {
                return false;
            }

            public function delete(string $key): void {}

            public function clear(): void {}

            public function getName(): string
            {
                return 'failing2';
            }
        };

        $fallback = new FallbackStorageAdapter([$failingAdapter1, $failingAdapter2]);

        expect(fn () => $fallback->read('key'))
            ->toThrow(StorageException::class, 'All storage adapters failed for read operation');
    });

    it('writes to all available adapters', function () {
        $adapter1 = new InMemoryStorageAdapter;
        $adapter2 = new InMemoryStorageAdapter;

        $fallback = new FallbackStorageAdapter([$adapter1, $adapter2]);
        $fallback->write('key', 'value');

        expect($adapter1->read('key'))->toBe('value')
            ->and($adapter2->read('key'))->toBe('value');
    });

    it('succeeds write if at least one adapter succeeds', function () {
        $failingAdapter = new class implements StorageAdapter
        {
            public function read(string $key): ?string
            {
                return null;
            }

            public function write(string $key, string $value, ?int $ttl = null): void
            {
                throw new Exception('Write failed');
            }

            public function exists(string $key): bool
            {
                return false;
            }

            public function delete(string $key): void {}

            public function clear(): void {}

            public function getName(): string
            {
                return 'failing';
            }
        };

        $workingAdapter = new InMemoryStorageAdapter;

        $fallback = new FallbackStorageAdapter([$failingAdapter, $workingAdapter]);

        expect(fn () => $fallback->write('key', 'value'))->not->toThrow(Exception::class);
        expect($workingAdapter->read('key'))->toBe('value');
    });

    it('throws exception when all adapters fail on write', function () {
        $failingAdapter1 = new class implements StorageAdapter
        {
            public function read(string $key): ?string
            {
                return null;
            }

            public function write(string $key, string $value, ?int $ttl = null): void
            {
                throw new Exception('Write failed');
            }

            public function exists(string $key): bool
            {
                return false;
            }

            public function delete(string $key): void {}

            public function clear(): void {}

            public function getName(): string
            {
                return 'failing1';
            }
        };

        $failingAdapter2 = new class implements StorageAdapter
        {
            public function read(string $key): ?string
            {
                return null;
            }

            public function write(string $key, string $value, ?int $ttl = null): void
            {
                throw new Exception('Write failed');
            }

            public function exists(string $key): bool
            {
                return false;
            }

            public function delete(string $key): void {}

            public function clear(): void {}

            public function getName(): string
            {
                return 'failing2';
            }
        };

        $fallback = new FallbackStorageAdapter([$failingAdapter1, $failingAdapter2]);

        expect(fn () => $fallback->write('key', 'value'))
            ->toThrow(StorageException::class, 'All storage adapters failed for write operation');
    });

    it('checks exists across all adapters', function () {
        $adapter1 = new InMemoryStorageAdapter;
        $adapter2 = new InMemoryStorageAdapter;
        $adapter2->write('key', 'value');

        $fallback = new FallbackStorageAdapter([$adapter1, $adapter2]);

        expect($fallback->exists('key'))->toBeTrue();
    });

    it('returns false for exists when key not found anywhere', function () {
        $adapter1 = new InMemoryStorageAdapter;
        $adapter2 = new InMemoryStorageAdapter;

        $fallback = new FallbackStorageAdapter([$adapter1, $adapter2]);

        expect($fallback->exists('nonexistent'))->toBeFalse();
    });

    it('deletes from all adapters', function () {
        $adapter1 = new InMemoryStorageAdapter;
        $adapter1->write('key', 'value');

        $adapter2 = new InMemoryStorageAdapter;
        $adapter2->write('key', 'value');

        $fallback = new FallbackStorageAdapter([$adapter1, $adapter2]);
        $fallback->delete('key');

        expect($adapter1->exists('key'))->toBeFalse()
            ->and($adapter2->exists('key'))->toBeFalse();
    });

    it('succeeds delete if at least one adapter succeeds', function () {
        $failingAdapter = new class implements StorageAdapter
        {
            public function read(string $key): ?string
            {
                return null;
            }

            public function write(string $key, string $value, ?int $ttl = null): void {}

            public function exists(string $key): bool
            {
                return false;
            }

            public function delete(string $key): void
            {
                throw new Exception('Delete failed');
            }

            public function clear(): void {}

            public function getName(): string
            {
                return 'failing';
            }
        };

        $workingAdapter = new InMemoryStorageAdapter;
        $workingAdapter->write('key', 'value');

        $fallback = new FallbackStorageAdapter([$failingAdapter, $workingAdapter]);

        expect(fn () => $fallback->delete('key'))->not->toThrow(Exception::class);
        expect($workingAdapter->exists('key'))->toBeFalse();
    });

    it('clears all adapters', function () {
        $adapter1 = new InMemoryStorageAdapter;
        $adapter1->write('key1', 'value1');

        $adapter2 = new InMemoryStorageAdapter;
        $adapter2->write('key2', 'value2');

        $fallback = new FallbackStorageAdapter([$adapter1, $adapter2]);
        $fallback->clear();

        expect($adapter1->exists('key1'))->toBeFalse()
            ->and($adapter2->exists('key2'))->toBeFalse();
    });

    it('can get list of adapters', function () {
        $adapter1 = new InMemoryStorageAdapter;
        $adapter2 = new NullStorageAdapter;

        $fallback = new FallbackStorageAdapter([$adapter1, $adapter2]);
        $adapters = $fallback->getAdapters();

        expect($adapters)->toHaveCount(2)
            ->and($adapters[0])->toBe($adapter1)
            ->and($adapters[1])->toBe($adapter2);
    });

    it('can create with logger', function () {
        $logger = new NullLogger;
        $adapters = [new InMemoryStorageAdapter];

        $fallback = new FallbackStorageAdapter($adapters, $logger);

        expect($fallback)->toBeInstanceOf(FallbackStorageAdapter::class);
    });

    it('re-indexes adapters array', function () {
        $adapters = [
            2 => new InMemoryStorageAdapter,
            5 => new NullStorageAdapter,
        ];

        $fallback = new FallbackStorageAdapter($adapters);
        $result = $fallback->getAdapters();

        expect(array_keys($result))->toBe([0, 1]);
    });
});

<?php

declare(strict_types=1);

use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\Decorators\RetryStorageDecorator;
use Farzai\Breaker\Storage\StorageAdapter;

describe('RetryStorageDecorator', function () {
    it('can create decorator with default settings', function () {
        $adapter = new InMemoryStorageAdapter;
        $decorator = new RetryStorageDecorator($adapter);

        expect($decorator)->toBeInstanceOf(RetryStorageDecorator::class);
    });

    it('can create decorator with custom settings', function () {
        $adapter = new InMemoryStorageAdapter;
        $decorator = new RetryStorageDecorator(
            $adapter,
            maxAttempts: 5,
            initialDelayMs: 200,
            multiplier: 3.0,
            useJitter: false
        );

        expect($decorator)->toBeInstanceOf(RetryStorageDecorator::class);
    });

    it('validates max attempts minimum', function () {
        $adapter = new InMemoryStorageAdapter;

        expect(fn () => new RetryStorageDecorator($adapter, maxAttempts: 0))
            ->toThrow(InvalidArgumentException::class, 'Max attempts must be at least 1');
    });

    it('validates initial delay minimum', function () {
        $adapter = new InMemoryStorageAdapter;

        expect(fn () => new RetryStorageDecorator($adapter, initialDelayMs: -1))
            ->toThrow(InvalidArgumentException::class, 'Initial delay must be non-negative');
    });

    it('validates multiplier minimum', function () {
        $adapter = new InMemoryStorageAdapter;

        expect(fn () => new RetryStorageDecorator($adapter, multiplier: 0.5))
            ->toThrow(InvalidArgumentException::class, 'Multiplier must be at least 1.0');
    });

    it('returns correct name', function () {
        $adapter = new InMemoryStorageAdapter;
        $decorator = new RetryStorageDecorator($adapter);

        expect($decorator->getName())->toBe('retry(memory)');
    });

    it('succeeds on first attempt for read', function () {
        $adapter = new InMemoryStorageAdapter;
        $adapter->write('key', 'value');

        $decorator = new RetryStorageDecorator($adapter);

        expect($decorator->read('key'))->toBe('value');
    });

    it('retries and succeeds on second attempt for read', function () {
        $attemptCount = 0;

        $adapter = new class($attemptCount) implements StorageAdapter
        {
            public function __construct(private &$count) {}

            public function read(string $key): ?string
            {
                $this->count++;
                if ($this->count === 1) {
                    throw new Exception('First attempt failed');
                }

                return 'value';
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
                return 'test';
            }
        };

        $decorator = new RetryStorageDecorator($adapter, maxAttempts: 3, initialDelayMs: 0);

        expect($decorator->read('key'))->toBe('value')
            ->and($attemptCount)->toBe(2);
    });

    it('throws exception after max attempts on read', function () {
        $adapter = new class implements StorageAdapter
        {
            public function read(string $key): ?string
            {
                throw new Exception('Always fails');
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
                return 'test';
            }
        };

        $decorator = new RetryStorageDecorator($adapter, maxAttempts: 3, initialDelayMs: 0);

        expect(fn () => $decorator->read('key'))
            ->toThrow(Exception::class, 'Always fails');
    });

    it('succeeds on first attempt for write', function () {
        $adapter = new InMemoryStorageAdapter;
        $decorator = new RetryStorageDecorator($adapter);

        $decorator->write('key', 'value');

        expect($adapter->read('key'))->toBe('value');
    });

    it('retries and succeeds for write', function () {
        $attemptCount = 0;

        $adapter = new class($attemptCount) implements StorageAdapter
        {
            public function __construct(private &$count) {}

            public function read(string $key): ?string
            {
                return null;
            }

            public function write(string $key, string $value, ?int $ttl = null): void
            {
                $this->count++;
                if ($this->count < 2) {
                    throw new Exception('Write failed');
                }
            }

            public function exists(string $key): bool
            {
                return false;
            }

            public function delete(string $key): void {}

            public function clear(): void {}

            public function getName(): string
            {
                return 'test';
            }
        };

        $decorator = new RetryStorageDecorator($adapter, maxAttempts: 3, initialDelayMs: 0);
        $decorator->write('key', 'value');

        expect($attemptCount)->toBe(2);
    });

    it('succeeds on first attempt for exists', function () {
        $adapter = new InMemoryStorageAdapter;
        $adapter->write('key', 'value');

        $decorator = new RetryStorageDecorator($adapter);

        expect($decorator->exists('key'))->toBeTrue();
    });

    it('succeeds on first attempt for delete', function () {
        $adapter = new InMemoryStorageAdapter;
        $adapter->write('key', 'value');

        $decorator = new RetryStorageDecorator($adapter);
        $decorator->delete('key');

        expect($adapter->exists('key'))->toBeFalse();
    });

    it('succeeds on first attempt for clear', function () {
        $adapter = new InMemoryStorageAdapter;
        $adapter->write('key', 'value');

        $decorator = new RetryStorageDecorator($adapter);
        $decorator->clear();

        expect($adapter->exists('key'))->toBeFalse();
    });

    it('retries correct number of times', function () {
        $attemptCount = 0;

        $adapter = new class($attemptCount) implements StorageAdapter
        {
            public function __construct(private &$count) {}

            public function read(string $key): ?string
            {
                $this->count++;
                throw new Exception('Always fails');
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
                return 'test';
            }
        };

        $decorator = new RetryStorageDecorator($adapter, maxAttempts: 5, initialDelayMs: 0);

        try {
            $decorator->read('key');
        } catch (Exception $e) {
            // Expected to fail
        }

        expect($attemptCount)->toBe(5);
    });

    it('does not sleep on last attempt', function () {
        $attemptCount = 0;
        $startTime = microtime(true);

        $adapter = new class($attemptCount) implements StorageAdapter
        {
            public function __construct(private &$count) {}

            public function read(string $key): ?string
            {
                $this->count++;
                throw new Exception('Always fails');
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
                return 'test';
            }
        };

        $decorator = new RetryStorageDecorator($adapter, maxAttempts: 2, initialDelayMs: 100);

        try {
            $decorator->read('key');
        } catch (Exception $e) {
            // Expected to fail
        }

        $elapsed = (microtime(true) - $startTime) * 1000;

        // Should have slept only once (between attempt 1 and 2), not after attempt 2
        expect($elapsed)->toBeLessThan(200); // Less than 2 * initialDelayMs
    });

    it('handles zero initial delay', function () {
        $adapter = new class implements StorageAdapter
        {
            public function read(string $key): ?string
            {
                throw new Exception('Fails');
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
                return 'test';
            }
        };

        $decorator = new RetryStorageDecorator($adapter, maxAttempts: 2, initialDelayMs: 0);

        expect(fn () => $decorator->read('key'))->toThrow(Exception::class);
    });

    it('works with single retry attempt', function () {
        $attemptCount = 0;

        $adapter = new class($attemptCount) implements StorageAdapter
        {
            public function __construct(private &$count) {}

            public function read(string $key): ?string
            {
                $this->count++;
                throw new Exception('Fails');
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
                return 'test';
            }
        };

        $decorator = new RetryStorageDecorator($adapter, maxAttempts: 1, initialDelayMs: 0);

        try {
            $decorator->read('key');
        } catch (Exception $e) {
            // Expected
        }

        expect($attemptCount)->toBe(1);
    });
});

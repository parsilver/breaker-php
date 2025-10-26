<?php

declare(strict_types=1);

use Farzai\Breaker\Exceptions\StorageException;
use Farzai\Breaker\Exceptions\StorageReadException;
use Farzai\Breaker\Exceptions\StorageWriteException;
use Farzai\Breaker\Storage\Adapters\Psr16StorageAdapter;
use Psr\SimpleCache\CacheInterface;

describe('Psr16StorageAdapter', function () {
    it('can create adapter with cache', function () {
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

        $adapter = new Psr16StorageAdapter($cache);

        expect($adapter)->toBeInstanceOf(Psr16StorageAdapter::class);
    });

    it('can create adapter with TTL', function () {
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

        $adapter = new Psr16StorageAdapter($cache, 3600);

        expect($adapter)->toBeInstanceOf(Psr16StorageAdapter::class);
    });

    it('returns correct name', function () {
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

        $adapter = new Psr16StorageAdapter($cache);

        expect($adapter->getName())->toBe('psr16');
    });

    it('can read from cache', function () {
        $cache = new class implements CacheInterface
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return 'cached-value';
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
                return true;
            }
        };

        $adapter = new Psr16StorageAdapter($cache);
        $result = $adapter->read('test-key');

        expect($result)->toBe('cached-value');
    });

    it('returns null when cache key does not exist', function () {
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

        $adapter = new Psr16StorageAdapter($cache);
        $result = $adapter->read('nonexistent-key');

        expect($result)->toBeNull();
    });

    it('can write to cache with default TTL', function () {
        $writtenKey = null;
        $writtenValue = null;
        $writtenTtl = null;

        $cache = new class($writtenKey, $writtenValue, $writtenTtl) implements CacheInterface
        {
            public function __construct(private &$key, private &$value, private &$ttl) {}

            public function get(string $key, mixed $default = null): mixed
            {
                return null;
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                $this->key = $key;
                $this->value = $value;
                $this->ttl = $ttl;

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

        $adapter = new Psr16StorageAdapter($cache, 7200);
        $adapter->write('test-key', 'test-value');

        expect($writtenKey)->toBe('test-key')
            ->and($writtenValue)->toBe('test-value')
            ->and($writtenTtl)->toBe(7200);
    });

    it('can write to cache with custom TTL', function () {
        $writtenTtl = null;

        $cache = new class($writtenTtl) implements CacheInterface
        {
            public function __construct(private &$ttl) {}

            public function get(string $key, mixed $default = null): mixed
            {
                return null;
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                $this->ttl = $ttl;

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

        $adapter = new Psr16StorageAdapter($cache);
        $adapter->write('test-key', 'test-value', 1800);

        expect($writtenTtl)->toBe(1800);
    });

    it('throws exception when cache set fails', function () {
        $cache = new class implements CacheInterface
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return null;
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                return false;
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

        $adapter = new Psr16StorageAdapter($cache);

        expect(fn () => $adapter->write('test-key', 'value'))
            ->toThrow(StorageWriteException::class);
    });

    it('throws exception when cache get returns non-string value', function () {
        $cache = new class implements CacheInterface
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return 123;
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
                return true;
            }
        };

        $adapter = new Psr16StorageAdapter($cache);

        expect(fn () => $adapter->read('test-key'))
            ->toThrow(StorageReadException::class, 'Expected string value from cache');
    });

    it('can check if key exists', function () {
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
                return $key === 'existing-key';
            }
        };

        $adapter = new Psr16StorageAdapter($cache);

        expect($adapter->exists('existing-key'))->toBeTrue()
            ->and($adapter->exists('nonexistent-key'))->toBeFalse();
    });

    it('can delete from cache', function () {
        $deletedKey = null;

        $cache = new class($deletedKey) implements CacheInterface
        {
            public function __construct(private &$key) {}

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
                $this->key = $key;

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

        $adapter = new Psr16StorageAdapter($cache);
        $adapter->delete('test-key');

        expect($deletedKey)->toBe('test-key');
    });

    it('throws exception when delete fails', function () {
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
                return false;
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

        $adapter = new Psr16StorageAdapter($cache);

        expect(fn () => $adapter->delete('test-key'))
            ->toThrow(StorageException::class);
    });

    it('can clear cache', function () {
        $cleared = false;

        $cache = new class($cleared) implements CacheInterface
        {
            public function __construct(private &$cleared) {}

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
                $this->cleared = true;

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

        $adapter = new Psr16StorageAdapter($cache);
        $adapter->clear();

        expect($cleared)->toBeTrue();
    });

    it('throws exception when clear fails', function () {
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
                return false;
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

        $adapter = new Psr16StorageAdapter($cache);

        expect(fn () => $adapter->clear())
            ->toThrow(StorageException::class);
    });

    it('can get underlying cache instance', function () {
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

        $adapter = new Psr16StorageAdapter($cache);

        expect($adapter->getCache())->toBe($cache);
    });
});

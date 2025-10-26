<?php

declare(strict_types=1);

use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\Decorators\MetricsCollector;
use Farzai\Breaker\Storage\Decorators\MetricsStorageDecorator;

describe('MetricsStorageDecoratorTest', function () {
    it('can create decorator with metrics collector', function () {
        $adapter = new InMemoryStorageAdapter;
        $metrics = new class implements MetricsCollector
        {
            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void {}

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $decorator = new MetricsStorageDecorator($adapter, $metrics);

        expect($decorator)->toBeInstanceOf(MetricsStorageDecorator::class);
    });

    it('returns correct name', function () {
        $adapter = new InMemoryStorageAdapter;
        $metrics = new class implements MetricsCollector
        {
            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void {}

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $decorator = new MetricsStorageDecorator($adapter, $metrics);

        expect($decorator->getName())->toBe('metrics(memory)');
    });

    it('records metrics for successful read', function () {
        $recordedOperation = null;
        $recordedSuccess = null;

        $adapter = new InMemoryStorageAdapter;
        $adapter->write('key', 'value');

        $metrics = new class($recordedOperation, $recordedSuccess) implements MetricsCollector
        {
            public function __construct(private &$operation, private &$success) {}

            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void
            {
                $this->operation = $operation;
                $this->success = $success;
            }

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $decorator = new MetricsStorageDecorator($adapter, $metrics);
        $result = $decorator->read('key');

        expect($result)->toBe('value')
            ->and($recordedOperation)->toBe('read')
            ->and($recordedSuccess)->toBeTrue();
    });

    it('records metrics for failed read', function () {
        $recordedOperation = null;
        $recordedSuccess = null;

        $adapter = new class implements \Farzai\Breaker\Storage\StorageAdapter
        {
            public function read(string $key): ?string
            {
                throw new Exception('Read failed');
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

        $metrics = new class($recordedOperation, $recordedSuccess) implements MetricsCollector
        {
            public function __construct(private &$operation, private &$success) {}

            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void
            {
                $this->operation = $operation;
                $this->success = $success;
            }

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $decorator = new MetricsStorageDecorator($adapter, $metrics);

        try {
            $decorator->read('key');
        } catch (Exception $e) {
            // Expected
        }

        expect($recordedOperation)->toBe('read')
            ->and($recordedSuccess)->toBeFalse();
    });

    it('records metrics for write operations', function () {
        $recordedOperation = null;

        $adapter = new InMemoryStorageAdapter;

        $metrics = new class($recordedOperation) implements MetricsCollector
        {
            public function __construct(private &$operation) {}

            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void
            {
                $this->operation = $operation;
            }

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $decorator = new MetricsStorageDecorator($adapter, $metrics);
        $decorator->write('key', 'value');

        expect($recordedOperation)->toBe('write');
    });

    it('records metrics for exists operations', function () {
        $recordedOperation = null;

        $adapter = new InMemoryStorageAdapter;

        $metrics = new class($recordedOperation) implements MetricsCollector
        {
            public function __construct(private &$operation) {}

            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void
            {
                $this->operation = $operation;
            }

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $decorator = new MetricsStorageDecorator($adapter, $metrics);
        $decorator->exists('key');

        expect($recordedOperation)->toBe('exists');
    });

    it('records metrics for delete operations', function () {
        $recordedOperation = null;

        $adapter = new InMemoryStorageAdapter;
        $adapter->write('key', 'value');

        $metrics = new class($recordedOperation) implements MetricsCollector
        {
            public function __construct(private &$operation) {}

            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void
            {
                $this->operation = $operation;
            }

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $decorator = new MetricsStorageDecorator($adapter, $metrics);
        $decorator->delete('key');

        expect($recordedOperation)->toBe('delete');
    });

    it('records metrics for clear operations', function () {
        $recordedOperation = null;

        $adapter = new InMemoryStorageAdapter;

        $metrics = new class($recordedOperation) implements MetricsCollector
        {
            public function __construct(private &$operation) {}

            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void
            {
                $this->operation = $operation;
            }

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $decorator = new MetricsStorageDecorator($adapter, $metrics);
        $decorator->clear();

        expect($recordedOperation)->toBe('clear');
    });

    it('records duration for operations', function () {
        $recordedDuration = null;

        $adapter = new InMemoryStorageAdapter;

        $metrics = new class($recordedDuration) implements MetricsCollector
        {
            public function __construct(private &$duration) {}

            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void
            {
                $this->duration = $durationMs;
            }

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $decorator = new MetricsStorageDecorator($adapter, $metrics);
        $decorator->read('key');

        expect($recordedDuration)->toBeGreaterThanOrEqual(0);
    });

    it('passes adapter name to metrics', function () {
        $recordedAdapter = null;

        $adapter = new InMemoryStorageAdapter;

        $metrics = new class($recordedAdapter) implements MetricsCollector
        {
            public function __construct(private &$adapter) {}

            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void
            {
                $this->adapter = $adapter;
            }

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $decorator = new MetricsStorageDecorator($adapter, $metrics);
        $decorator->read('key');

        expect($recordedAdapter)->toBe('memory');
    });
});

<?php

declare(strict_types=1);

use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\CircuitStateRepository;
use Farzai\Breaker\Storage\Decorators\MetricsCollector;
use Farzai\Breaker\Storage\StorageBuilder;
use Psr\Log\NullLogger;

describe('StorageBuilder', function () {
    it('can create a builder', function () {
        $adapter = new InMemoryStorageAdapter;
        $builder = new StorageBuilder($adapter);

        expect($builder)->toBeInstanceOf(StorageBuilder::class);
    });

    it('can build and return the adapter', function () {
        $adapter = new InMemoryStorageAdapter;
        $builder = new StorageBuilder($adapter);

        $result = $builder->build();

        expect($result)->toBeInstanceOf(InMemoryStorageAdapter::class);
    });

    it('can build a repository', function () {
        $adapter = new InMemoryStorageAdapter;
        $builder = new StorageBuilder($adapter);

        $repository = $builder->buildRepository();

        expect($repository)->toBeInstanceOf(CircuitStateRepository::class);
    });

    it('can add logging decorator', function () {
        $adapter = new InMemoryStorageAdapter;
        $logger = new NullLogger;
        $builder = new StorageBuilder($adapter);

        $result = $builder->withLogging($logger);

        expect($result)->toBe($builder); // Returns self for chaining
    });

    it('can add metrics decorator', function () {
        $adapter = new InMemoryStorageAdapter;
        $metrics = new class implements MetricsCollector
        {
            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void {}

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $builder = new StorageBuilder($adapter);

        $result = $builder->withMetrics($metrics);

        expect($result)->toBe($builder);
    });

    it('can add retry decorator', function () {
        $adapter = new InMemoryStorageAdapter;
        $builder = new StorageBuilder($adapter);

        $result = $builder->withRetry();

        expect($result)->toBe($builder);
    });

    it('can add retry decorator with custom parameters', function () {
        $adapter = new InMemoryStorageAdapter;
        $builder = new StorageBuilder($adapter);

        $result = $builder->withRetry(
            maxAttempts: 5,
            initialDelayMs: 200,
            multiplier: 3.0,
            useJitter: false
        );

        expect($result)->toBe($builder);
    });

    it('supports fluent chaining', function () {
        $adapter = new InMemoryStorageAdapter;
        $logger = new NullLogger;
        $metrics = new class implements MetricsCollector
        {
            public function recordOperation(string $operation, string $adapter, float $durationMs, bool $success, array $tags = []): void {}

            public function increment(string $metric, int $value = 1, array $tags = []): void {}
        };

        $builder = (new StorageBuilder($adapter))
            ->withLogging($logger)
            ->withMetrics($metrics)
            ->withRetry(3);

        expect($builder)->toBeInstanceOf(StorageBuilder::class);
    });

    it('can build decorated adapter', function () {
        $adapter = new InMemoryStorageAdapter;
        $logger = new NullLogger;

        $decorated = (new StorageBuilder($adapter))
            ->withLogging($logger)
            ->build();

        expect($decorated)->not->toBe($adapter); // Should be wrapped
    });

    it('can build repository from decorated adapter', function () {
        $adapter = new InMemoryStorageAdapter;
        $logger = new NullLogger;

        $repository = (new StorageBuilder($adapter))
            ->withLogging($logger)
            ->withRetry(3)
            ->buildRepository();

        expect($repository)->toBeInstanceOf(CircuitStateRepository::class);
    });
});

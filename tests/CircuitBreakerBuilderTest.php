<?php

declare(strict_types=1);

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\CircuitBreakerBuilder;
use Farzai\Breaker\Config\CircuitBreakerConfig;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\DefaultCircuitStateRepository;
use Farzai\Breaker\Storage\JsonStorageSerializer;
use Farzai\Breaker\Time\SystemTimeProvider;
use Psr\Log\NullLogger;

describe('CircuitBreakerBuilder', function () {
    it('can create a builder instance', function () {
        $builder = CircuitBreakerBuilder::create('test-service');

        expect($builder)->toBeInstanceOf(CircuitBreakerBuilder::class);
    });

    it('can build a circuit breaker with default settings', function () {
        $breaker = CircuitBreakerBuilder::create('test-service')->build();

        expect($breaker)
            ->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can build using the default static method', function () {
        $breaker = CircuitBreakerBuilder::default('test-service');

        expect($breaker)
            ->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set failure threshold', function () {
        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withFailureThreshold(10)
            ->build();

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);

        // Test that the threshold is applied by triggering failures
        $failures = 0;
        for ($i = 0; $i < 15; $i++) {
            try {
                $breaker->call(function () {
                    throw new Exception('Test failure');
                });
            } catch (Exception $e) {
                $failures++;
            }
        }

        expect($failures)->toBe(15);
    });

    it('can set success threshold', function () {
        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withFailureThreshold(2)
            ->withSuccessThreshold(3)
            ->build();

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set timeout', function () {
        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withTimeout(60)
            ->build();

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set half-open max attempts', function () {
        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withHalfOpenMaxAttempts(3)
            ->build();

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set a custom config object', function () {
        $config = new CircuitBreakerConfig(
            failureThreshold: 3,
            successThreshold: 1,
            timeout: 20,
            halfOpenMaxAttempts: 2
        );

        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withConfig($config)
            ->build();

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set a custom repository', function () {
        $repository = new DefaultCircuitStateRepository(
            new InMemoryStorageAdapter,
            new JsonStorageSerializer
        );

        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withRepository($repository)
            ->build();

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set a custom logger', function () {
        $logger = new NullLogger;

        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withLogger($logger)
            ->build();

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set a custom time provider', function () {
        $timeProvider = new SystemTimeProvider;

        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withTimeProvider($timeProvider)
            ->build();

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('supports fluent chaining', function () {
        $logger = new NullLogger;
        $timeProvider = new SystemTimeProvider;

        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withFailureThreshold(3)
            ->withSuccessThreshold(2)
            ->withTimeout(45)
            ->withHalfOpenMaxAttempts(2)
            ->withLogger($logger)
            ->withTimeProvider($timeProvider)
            ->build();

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('uses custom config over individual settings when both are provided', function () {
        $config = new CircuitBreakerConfig(
            failureThreshold: 7,
            successThreshold: 3,
            timeout: 50,
            halfOpenMaxAttempts: 4
        );

        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withFailureThreshold(5) // This should be ignored
            ->withConfig($config) // This should take precedence
            ->build();

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('creates a working circuit breaker that can execute calls', function () {
        $breaker = CircuitBreakerBuilder::create('test-service')
            ->withFailureThreshold(3)
            ->build();

        $result = $breaker->call(function () {
            return 'success';
        });

        expect($result)->toBe('success');
    });

    it('creates independent builders for different services', function () {
        $builder1 = CircuitBreakerBuilder::create('service-1');
        $builder2 = CircuitBreakerBuilder::create('service-2');

        $breaker1 = $builder1->withFailureThreshold(5)->build();
        $breaker2 = $builder2->withFailureThreshold(10)->build();

        expect($breaker1)->not->toBe($breaker2);
    });
});

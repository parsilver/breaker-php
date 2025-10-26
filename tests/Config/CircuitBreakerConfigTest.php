<?php

declare(strict_types=1);

use Farzai\Breaker\Config\CircuitBreakerConfig;

describe('CircuitBreakerConfig', function () {
    it('can create config with default values', function () {
        $config = new CircuitBreakerConfig;

        expect($config->failureThreshold)->toBe(5)
            ->and($config->successThreshold)->toBe(2)
            ->and($config->timeout)->toBe(30)
            ->and($config->halfOpenMaxAttempts)->toBe(1);
    });

    it('can create config with custom values', function () {
        $config = new CircuitBreakerConfig(
            failureThreshold: 10,
            successThreshold: 3,
            timeout: 60,
            halfOpenMaxAttempts: 2
        );

        expect($config->failureThreshold)->toBe(10)
            ->and($config->successThreshold)->toBe(3)
            ->and($config->timeout)->toBe(60)
            ->and($config->halfOpenMaxAttempts)->toBe(2);
    });

    it('can create config from array', function () {
        $config = CircuitBreakerConfig::fromArray([
            'failure_threshold' => 7,
            'success_threshold' => 4,
            'timeout' => 45,
            'half_open_max_attempts' => 3,
        ]);

        expect($config->failureThreshold)->toBe(7)
            ->and($config->successThreshold)->toBe(4)
            ->and($config->timeout)->toBe(45)
            ->and($config->halfOpenMaxAttempts)->toBe(3);
    });

    it('uses default values when fromArray is called with empty array', function () {
        $config = CircuitBreakerConfig::fromArray([]);

        expect($config->failureThreshold)->toBe(5)
            ->and($config->successThreshold)->toBe(2)
            ->and($config->timeout)->toBe(30)
            ->and($config->halfOpenMaxAttempts)->toBe(1);
    });

    it('uses default values for missing keys in fromArray', function () {
        $config = CircuitBreakerConfig::fromArray([
            'failure_threshold' => 8,
        ]);

        expect($config->failureThreshold)->toBe(8)
            ->and($config->successThreshold)->toBe(2)
            ->and($config->timeout)->toBe(30)
            ->and($config->halfOpenMaxAttempts)->toBe(1);
    });

    it('can convert to array', function () {
        $config = new CircuitBreakerConfig(
            failureThreshold: 6,
            successThreshold: 3,
            timeout: 50,
            halfOpenMaxAttempts: 2
        );

        $array = $config->toArray();

        expect($array)->toBe([
            'failure_threshold' => 6,
            'success_threshold' => 3,
            'timeout' => 50,
            'half_open_max_attempts' => 2,
        ]);
    });

    it('is immutable with withFailureThreshold', function () {
        $original = new CircuitBreakerConfig(failureThreshold: 5);
        $modified = $original->withFailureThreshold(10);

        expect($original->failureThreshold)->toBe(5)
            ->and($modified->failureThreshold)->toBe(10)
            ->and($original)->not->toBe($modified);
    });

    it('is immutable with withSuccessThreshold', function () {
        $original = new CircuitBreakerConfig(successThreshold: 2);
        $modified = $original->withSuccessThreshold(5);

        expect($original->successThreshold)->toBe(2)
            ->and($modified->successThreshold)->toBe(5)
            ->and($original)->not->toBe($modified);
    });

    it('is immutable with withTimeout', function () {
        $original = new CircuitBreakerConfig(timeout: 30);
        $modified = $original->withTimeout(60);

        expect($original->timeout)->toBe(30)
            ->and($modified->timeout)->toBe(60)
            ->and($original)->not->toBe($modified);
    });

    it('is immutable with withHalfOpenMaxAttempts', function () {
        $original = new CircuitBreakerConfig(halfOpenMaxAttempts: 1);
        $modified = $original->withHalfOpenMaxAttempts(3);

        expect($original->halfOpenMaxAttempts)->toBe(1)
            ->and($modified->halfOpenMaxAttempts)->toBe(3)
            ->and($original)->not->toBe($modified);
    });

    it('preserves other properties when using with methods', function () {
        $config = new CircuitBreakerConfig(
            failureThreshold: 5,
            successThreshold: 2,
            timeout: 30,
            halfOpenMaxAttempts: 1
        );

        $modified = $config->withFailureThreshold(10);

        expect($modified->failureThreshold)->toBe(10)
            ->and($modified->successThreshold)->toBe(2)
            ->and($modified->timeout)->toBe(30)
            ->and($modified->halfOpenMaxAttempts)->toBe(1);
    });

    it('can chain with methods', function () {
        $config = new CircuitBreakerConfig;

        $modified = $config
            ->withFailureThreshold(10)
            ->withSuccessThreshold(4)
            ->withTimeout(90)
            ->withHalfOpenMaxAttempts(5);

        expect($modified->failureThreshold)->toBe(10)
            ->and($modified->successThreshold)->toBe(4)
            ->and($modified->timeout)->toBe(90)
            ->and($modified->halfOpenMaxAttempts)->toBe(5);
    });

    it('is readonly and cannot be modified directly', function () {
        $config = new CircuitBreakerConfig;

        expect(function () use ($config) {
            $config->failureThreshold = 10; // @phpstan-ignore-line
        })->toThrow(Error::class);
    });

    it('validates failure threshold minimum', function () {
        expect(fn () => new CircuitBreakerConfig(failureThreshold: 0))
            ->toThrow(InvalidArgumentException::class, 'Failure threshold must be at least 1');
    });

    it('validates failure threshold maximum', function () {
        expect(fn () => new CircuitBreakerConfig(failureThreshold: 1001))
            ->toThrow(InvalidArgumentException::class, 'Failure threshold must be at most 1000');
    });

    it('validates success threshold minimum', function () {
        expect(fn () => new CircuitBreakerConfig(successThreshold: 0))
            ->toThrow(InvalidArgumentException::class, 'Success threshold must be at least 1');
    });

    it('validates success threshold maximum', function () {
        expect(fn () => new CircuitBreakerConfig(successThreshold: 101))
            ->toThrow(InvalidArgumentException::class, 'Success threshold must be at most 100');
    });

    it('validates timeout minimum', function () {
        expect(fn () => new CircuitBreakerConfig(timeout: 0))
            ->toThrow(InvalidArgumentException::class, 'Timeout must be at least 1 second');
    });

    it('validates timeout maximum', function () {
        expect(fn () => new CircuitBreakerConfig(timeout: 3601))
            ->toThrow(InvalidArgumentException::class, 'Timeout must be at most 3600 seconds');
    });

    it('validates half-open max attempts minimum', function () {
        expect(fn () => new CircuitBreakerConfig(halfOpenMaxAttempts: 0))
            ->toThrow(InvalidArgumentException::class, 'Half-open max attempts must be at least 1');
    });

    it('validates half-open max attempts maximum', function () {
        expect(fn () => new CircuitBreakerConfig(halfOpenMaxAttempts: 11))
            ->toThrow(InvalidArgumentException::class, 'Half-open max attempts must be at most 10');
    });
});

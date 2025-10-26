<?php

declare(strict_types=1);

use Farzai\Breaker\Config\CircuitBreakerConfig;
use Farzai\Breaker\Config\ConfigValidator;

describe('ConfigValidator', function () {
    it('validates valid configuration', function () {
        $config = new CircuitBreakerConfig(
            failureThreshold: 5,
            successThreshold: 2,
            timeout: 30,
            halfOpenMaxAttempts: 1
        );

        expect(fn () => ConfigValidator::validate($config))->not->toThrow(Exception::class);
    });

    it('validates configuration with minimum values', function () {
        $config = new CircuitBreakerConfig(
            failureThreshold: 1,
            successThreshold: 1,
            timeout: 1,
            halfOpenMaxAttempts: 1
        );

        expect(fn () => ConfigValidator::validate($config))->not->toThrow(Exception::class);
    });

    it('validates configuration with maximum values', function () {
        $config = new CircuitBreakerConfig(
            failureThreshold: 1000,
            successThreshold: 100,
            timeout: 3600,
            halfOpenMaxAttempts: 10
        );

        expect(fn () => ConfigValidator::validate($config))->not->toThrow(Exception::class);
    });

    it('throws exception for failure threshold less than 1', function () {
        expect(fn () => new CircuitBreakerConfig(failureThreshold: 0))
            ->toThrow(InvalidArgumentException::class, 'Failure threshold must be at least 1');
    });

    it('throws exception for failure threshold greater than 1000', function () {
        expect(fn () => new CircuitBreakerConfig(failureThreshold: 1001))
            ->toThrow(InvalidArgumentException::class, 'Failure threshold must be at most 1000');
    });

    it('throws exception for negative failure threshold', function () {
        expect(fn () => new CircuitBreakerConfig(failureThreshold: -1))
            ->toThrow(InvalidArgumentException::class, 'Failure threshold must be at least 1');
    });

    it('throws exception for success threshold less than 1', function () {
        expect(fn () => new CircuitBreakerConfig(successThreshold: 0))
            ->toThrow(InvalidArgumentException::class, 'Success threshold must be at least 1');
    });

    it('throws exception for success threshold greater than 100', function () {
        expect(fn () => new CircuitBreakerConfig(successThreshold: 101))
            ->toThrow(InvalidArgumentException::class, 'Success threshold must be at most 100');
    });

    it('throws exception for negative success threshold', function () {
        expect(fn () => new CircuitBreakerConfig(successThreshold: -5))
            ->toThrow(InvalidArgumentException::class, 'Success threshold must be at least 1');
    });

    it('throws exception for timeout less than 1', function () {
        expect(fn () => new CircuitBreakerConfig(timeout: 0))
            ->toThrow(InvalidArgumentException::class, 'Timeout must be at least 1 second');
    });

    it('throws exception for timeout greater than 3600', function () {
        expect(fn () => new CircuitBreakerConfig(timeout: 3601))
            ->toThrow(InvalidArgumentException::class, 'Timeout must be at most 3600 seconds');
    });

    it('throws exception for negative timeout', function () {
        expect(fn () => new CircuitBreakerConfig(timeout: -10))
            ->toThrow(InvalidArgumentException::class, 'Timeout must be at least 1 second');
    });

    it('throws exception for half-open max attempts less than 1', function () {
        expect(fn () => new CircuitBreakerConfig(halfOpenMaxAttempts: 0))
            ->toThrow(InvalidArgumentException::class, 'Half-open max attempts must be at least 1');
    });

    it('throws exception for half-open max attempts greater than 10', function () {
        expect(fn () => new CircuitBreakerConfig(halfOpenMaxAttempts: 11))
            ->toThrow(InvalidArgumentException::class, 'Half-open max attempts must be at most 10');
    });

    it('throws exception for negative half-open max attempts', function () {
        expect(fn () => new CircuitBreakerConfig(halfOpenMaxAttempts: -2))
            ->toThrow(InvalidArgumentException::class, 'Half-open max attempts must be at least 1');
    });

    it('provides descriptive error message with actual value for failure threshold', function () {
        expect(fn () => new CircuitBreakerConfig(failureThreshold: 2000))
            ->toThrow(InvalidArgumentException::class, 'got 2000');
    });

    it('provides descriptive error message with actual value for success threshold', function () {
        expect(fn () => new CircuitBreakerConfig(successThreshold: 150))
            ->toThrow(InvalidArgumentException::class, 'got 150');
    });

    it('provides descriptive error message with actual value for timeout', function () {
        expect(fn () => new CircuitBreakerConfig(timeout: 5000))
            ->toThrow(InvalidArgumentException::class, 'got 5000');
    });

    it('provides descriptive error message with actual value for half-open max attempts', function () {
        expect(fn () => new CircuitBreakerConfig(halfOpenMaxAttempts: 50))
            ->toThrow(InvalidArgumentException::class, 'got 50');
    });
});

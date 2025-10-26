<?php

declare(strict_types=1);

use Farzai\Breaker\Metrics\CircuitMetrics;

describe('CircuitMetrics', function () {
    it('can create metrics with default values', function () {
        $metrics = new CircuitMetrics;

        expect($metrics->totalCalls)->toBe(0)
            ->and($metrics->successfulCalls)->toBe(0)
            ->and($metrics->failedCalls)->toBe(0)
            ->and($metrics->rejectedCalls)->toBe(0)
            ->and($metrics->fallbackCalls)->toBe(0)
            ->and($metrics->stateTransitions)->toBe(0)
            ->and($metrics->timeInClosed)->toBe(0)
            ->and($metrics->timeInOpen)->toBe(0)
            ->and($metrics->timeInHalfOpen)->toBe(0)
            ->and($metrics->lastStateChangeTime)->toBeNull()
            ->and($metrics->lastSuccessTime)->toBeNull()
            ->and($metrics->lastFailureTime)->toBeNull();
    });

    it('can create metrics with custom values', function () {
        $metrics = new CircuitMetrics(
            totalCalls: 100,
            successfulCalls: 80,
            failedCalls: 20,
            rejectedCalls: 5,
            fallbackCalls: 10,
            stateTransitions: 3,
            timeInClosed: 1000,
            timeInOpen: 500,
            timeInHalfOpen: 200,
            lastStateChangeTime: 1234567890,
            lastSuccessTime: 1234567891,
            lastFailureTime: 1234567892
        );

        expect($metrics->totalCalls)->toBe(100)
            ->and($metrics->successfulCalls)->toBe(80)
            ->and($metrics->failedCalls)->toBe(20);
    });

    it('calculates success rate correctly', function () {
        $metrics = new CircuitMetrics(
            totalCalls: 100,
            successfulCalls: 80
        );

        expect($metrics->getSuccessRate())->toBe(80.0);
    });

    it('returns zero success rate when no calls', function () {
        $metrics = new CircuitMetrics;

        expect($metrics->getSuccessRate())->toBe(0.0);
    });

    it('calculates failure rate correctly', function () {
        $metrics = new CircuitMetrics(
            totalCalls: 100,
            failedCalls: 25
        );

        expect($metrics->getFailureRate())->toBe(25.0);
    });

    it('returns zero failure rate when no calls', function () {
        $metrics = new CircuitMetrics;

        expect($metrics->getFailureRate())->toBe(0.0);
    });

    it('calculates rejection rate correctly', function () {
        $metrics = new CircuitMetrics(
            totalCalls: 100,
            rejectedCalls: 15
        );

        expect($metrics->getRejectionRate())->toBe(15.0);
    });

    it('returns zero rejection rate when no calls', function () {
        $metrics = new CircuitMetrics;

        expect($metrics->getRejectionRate())->toBe(0.0);
    });

    it('converts to array correctly', function () {
        $metrics = new CircuitMetrics(
            totalCalls: 50,
            successfulCalls: 40,
            failedCalls: 10,
            rejectedCalls: 5,
            fallbackCalls: 3,
            stateTransitions: 2
        );

        $array = $metrics->toArray();

        expect($array)->toBeArray()
            ->and($array['total_calls'])->toBe(50)
            ->and($array['successful_calls'])->toBe(40)
            ->and($array['failed_calls'])->toBe(10)
            ->and($array['success_rate'])->toBe(80.0)
            ->and($array['failure_rate'])->toBe(20.0);
    });

    it('is immutable with withSuccess', function () {
        $original = new CircuitMetrics;
        $modified = $original->withSuccess(1234567890);

        expect($original->totalCalls)->toBe(0)
            ->and($original->successfulCalls)->toBe(0)
            ->and($modified->totalCalls)->toBe(1)
            ->and($modified->successfulCalls)->toBe(1)
            ->and($modified->lastSuccessTime)->toBe(1234567890)
            ->and($original)->not->toBe($modified);
    });

    it('is immutable with withFailure', function () {
        $original = new CircuitMetrics;
        $modified = $original->withFailure(1234567890);

        expect($original->totalCalls)->toBe(0)
            ->and($original->failedCalls)->toBe(0)
            ->and($modified->totalCalls)->toBe(1)
            ->and($modified->failedCalls)->toBe(1)
            ->and($modified->lastFailureTime)->toBe(1234567890)
            ->and($original)->not->toBe($modified);
    });

    it('is immutable with withRejection', function () {
        $original = new CircuitMetrics;
        $modified = $original->withRejection();

        expect($original->totalCalls)->toBe(0)
            ->and($original->rejectedCalls)->toBe(0)
            ->and($modified->totalCalls)->toBe(1)
            ->and($modified->rejectedCalls)->toBe(1)
            ->and($original)->not->toBe($modified);
    });

    it('is immutable with withFallback', function () {
        $original = new CircuitMetrics;
        $modified = $original->withFallback();

        expect($original->fallbackCalls)->toBe(0)
            ->and($modified->fallbackCalls)->toBe(1)
            ->and($modified->totalCalls)->toBe(0) // Fallback doesn't increment total
            ->and($original)->not->toBe($modified);
    });

    it('is immutable with withStateTransition', function () {
        $original = new CircuitMetrics;
        $modified = $original->withStateTransition(1234567890);

        expect($original->stateTransitions)->toBe(0)
            ->and($modified->stateTransitions)->toBe(1)
            ->and($modified->lastStateChangeTime)->toBe(1234567890)
            ->and($original)->not->toBe($modified);
    });

    it('can chain multiple operations', function () {
        $metrics = new CircuitMetrics;

        $updated = $metrics
            ->withSuccess(1000)
            ->withSuccess(2000)
            ->withFailure(3000)
            ->withRejection()
            ->withStateTransition(4000);

        expect($updated->totalCalls)->toBe(4)
            ->and($updated->successfulCalls)->toBe(2)
            ->and($updated->failedCalls)->toBe(1)
            ->and($updated->rejectedCalls)->toBe(1)
            ->and($updated->stateTransitions)->toBe(1);
    });

    it('preserves other properties when using with methods', function () {
        $metrics = new CircuitMetrics(
            totalCalls: 50,
            successfulCalls: 40,
            failedCalls: 10,
            rejectedCalls: 5
        );

        $modified = $metrics->withSuccess(9999);

        expect($modified->failedCalls)->toBe(10)
            ->and($modified->rejectedCalls)->toBe(5);
    });

    it('is readonly and cannot be modified directly', function () {
        $metrics = new CircuitMetrics;

        expect(function () use ($metrics) {
            $metrics->totalCalls = 100; // @phpstan-ignore-line
        })->toThrow(Error::class);
    });

    it('calculates rates with decimal precision', function () {
        $metrics = new CircuitMetrics(
            totalCalls: 3,
            successfulCalls: 1,
            failedCalls: 1,
            rejectedCalls: 1
        );

        $successRate = $metrics->getSuccessRate();
        $failureRate = $metrics->getFailureRate();
        $rejectionRate = $metrics->getRejectionRate();

        expect($successRate)->toBeGreaterThan(33.0)
            ->and($successRate)->toBeLessThan(34.0)
            ->and($failureRate)->toBeGreaterThan(33.0)
            ->and($failureRate)->toBeLessThan(34.0)
            ->and($rejectionRate)->toBeGreaterThan(33.0)
            ->and($rejectionRate)->toBeLessThan(34.0);
    });

    it('is immutable with withIncrementedTotalCalls', function () {
        $original = new CircuitMetrics(
            totalCalls: 5,
            successfulCalls: 3,
            failedCalls: 2,
            rejectedCalls: 1
        );

        $modified = $original->withIncrementedTotalCalls();

        // Original unchanged
        expect($original->totalCalls)->toBe(5)
            ->and($original->successfulCalls)->toBe(3)
            ->and($original->failedCalls)->toBe(2);

        // New instance has incremented total calls
        expect($modified->totalCalls)->toBe(6)
            ->and($modified->successfulCalls)->toBe(3) // Unchanged
            ->and($modified->failedCalls)->toBe(2) // Unchanged
            ->and($modified->rejectedCalls)->toBe(1); // Unchanged

        // Verify it's a different instance
        expect($original)->not->toBe($modified);
    });

    it('withIncrementedTotalCalls preserves all other properties', function () {
        $original = new CircuitMetrics(
            totalCalls: 10,
            successfulCalls: 6,
            failedCalls: 3,
            rejectedCalls: 1,
            fallbackCalls: 2,
            stateTransitions: 5,
            timeInClosed: 1000,
            timeInOpen: 500,
            timeInHalfOpen: 200,
            lastStateChangeTime: 1234567890,
            lastSuccessTime: 1234567891,
            lastFailureTime: 1234567892
        );

        $modified = $original->withIncrementedTotalCalls();

        // Only totalCalls should change
        expect($modified->totalCalls)->toBe(11)
            ->and($modified->successfulCalls)->toBe(6)
            ->and($modified->failedCalls)->toBe(3)
            ->and($modified->rejectedCalls)->toBe(1)
            ->and($modified->fallbackCalls)->toBe(2)
            ->and($modified->stateTransitions)->toBe(5)
            ->and($modified->timeInClosed)->toBe(1000)
            ->and($modified->timeInOpen)->toBe(500)
            ->and($modified->timeInHalfOpen)->toBe(200)
            ->and($modified->lastStateChangeTime)->toBe(1234567890)
            ->and($modified->lastSuccessTime)->toBe(1234567891)
            ->and($modified->lastFailureTime)->toBe(1234567892);
    });

    it('can chain withIncrementedTotalCalls multiple times', function () {
        $metrics = new CircuitMetrics;

        $updated = $metrics
            ->withIncrementedTotalCalls()
            ->withIncrementedTotalCalls()
            ->withIncrementedTotalCalls();

        expect($updated->totalCalls)->toBe(3)
            ->and($updated->successfulCalls)->toBe(0)
            ->and($updated->failedCalls)->toBe(0)
            ->and($updated->rejectedCalls)->toBe(0);
    });
});

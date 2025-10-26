<?php

declare(strict_types=1);

use Farzai\Breaker\Metrics\InMemoryMetricsCollector;

describe('InMemoryMetricsCollector', function () {
    it('can create a collector', function () {
        $collector = new InMemoryMetricsCollector;

        expect($collector)->toBeInstanceOf(InMemoryMetricsCollector::class);
    });

    it('starts with empty metrics', function () {
        $collector = new InMemoryMetricsCollector;
        $metrics = $collector->getMetrics();

        expect($metrics->totalCalls)->toBe(0)
            ->and($metrics->successfulCalls)->toBe(0)
            ->and($metrics->failedCalls)->toBe(0)
            ->and($metrics->rejectedCalls)->toBe(0)
            ->and($metrics->fallbackCalls)->toBe(0)
            ->and($metrics->stateTransitions)->toBe(0);
    });

    it('records success', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordSuccess(1234567890);

        $metrics = $collector->getMetrics();

        expect($metrics->totalCalls)->toBe(1)
            ->and($metrics->successfulCalls)->toBe(1)
            ->and($metrics->lastSuccessTime)->toBe(1234567890);
    });

    it('records failure', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordFailure(1234567890);

        $metrics = $collector->getMetrics();

        expect($metrics->totalCalls)->toBe(1)
            ->and($metrics->failedCalls)->toBe(1)
            ->and($metrics->lastFailureTime)->toBe(1234567890);
    });

    it('records rejection', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordRejection();

        $metrics = $collector->getMetrics();

        expect($metrics->totalCalls)->toBe(1)
            ->and($metrics->rejectedCalls)->toBe(1);
    });

    it('records fallback', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordFallback();

        $metrics = $collector->getMetrics();

        expect($metrics->fallbackCalls)->toBe(1)
            ->and($metrics->totalCalls)->toBe(0); // Fallback doesn't increment total
    });

    it('records state transition', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordStateTransition(1234567890);

        $metrics = $collector->getMetrics();

        expect($metrics->stateTransitions)->toBe(1)
            ->and($metrics->lastStateChangeTime)->toBe(1234567890);
    });

    it('can record multiple successes', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordSuccess(1000);
        $collector->recordSuccess(2000);
        $collector->recordSuccess(3000);

        $metrics = $collector->getMetrics();

        expect($metrics->totalCalls)->toBe(3)
            ->and($metrics->successfulCalls)->toBe(3)
            ->and($metrics->lastSuccessTime)->toBe(3000);
    });

    it('can record multiple failures', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordFailure(1000);
        $collector->recordFailure(2000);

        $metrics = $collector->getMetrics();

        expect($metrics->totalCalls)->toBe(2)
            ->and($metrics->failedCalls)->toBe(2)
            ->and($metrics->lastFailureTime)->toBe(2000);
    });

    it('can record mixed operations', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordSuccess(1000);
        $collector->recordSuccess(2000);
        $collector->recordFailure(3000);
        $collector->recordRejection();
        $collector->recordFallback();
        $collector->recordStateTransition(4000);

        $metrics = $collector->getMetrics();

        expect($metrics->totalCalls)->toBe(4)
            ->and($metrics->successfulCalls)->toBe(2)
            ->and($metrics->failedCalls)->toBe(1)
            ->and($metrics->rejectedCalls)->toBe(1)
            ->and($metrics->fallbackCalls)->toBe(1)
            ->and($metrics->stateTransitions)->toBe(1);
    });

    it('can reset metrics', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordSuccess(1000);
        $collector->recordFailure(2000);
        $collector->recordRejection();

        $collector->reset();

        $metrics = $collector->getMetrics();

        expect($metrics->totalCalls)->toBe(0)
            ->and($metrics->successfulCalls)->toBe(0)
            ->and($metrics->failedCalls)->toBe(0)
            ->and($metrics->rejectedCalls)->toBe(0);
    });

    it('returns new metrics instance after reset', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordSuccess(1000);

        $before = $collector->getMetrics();
        $collector->reset();
        $after = $collector->getMetrics();

        expect($before)->not->toBe($after)
            ->and($before->totalCalls)->toBe(1)
            ->and($after->totalCalls)->toBe(0);
    });

    it('calculates success rate correctly', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordSuccess(1000);
        $collector->recordSuccess(2000);
        $collector->recordSuccess(3000);
        $collector->recordSuccess(4000);
        $collector->recordFailure(5000);

        $metrics = $collector->getMetrics();

        expect($metrics->getSuccessRate())->toBe(80.0);
    });

    it('calculates failure rate correctly', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordSuccess(1000);
        $collector->recordFailure(2000);
        $collector->recordFailure(3000);

        $metrics = $collector->getMetrics();
        $failureRate = $metrics->getFailureRate();

        expect($failureRate)->toBeGreaterThan(66.0)
            ->and($failureRate)->toBeLessThan(67.0);
    });

    it('returns immutable metrics', function () {
        $collector = new InMemoryMetricsCollector;
        $collector->recordSuccess(1000);

        $metrics1 = $collector->getMetrics();
        $metrics2 = $collector->getMetrics();

        // Each call returns the same instance (readonly)
        expect($metrics1)->toBe($metrics2);
    });

    it('updates metrics state after each operation', function () {
        $collector = new InMemoryMetricsCollector;

        $initial = $collector->getMetrics();
        expect($initial->totalCalls)->toBe(0);

        $collector->recordSuccess(1000);
        $afterSuccess = $collector->getMetrics();
        expect($afterSuccess->totalCalls)->toBe(1);

        $collector->recordFailure(2000);
        $afterFailure = $collector->getMetrics();
        expect($afterFailure->totalCalls)->toBe(2);
    });
});

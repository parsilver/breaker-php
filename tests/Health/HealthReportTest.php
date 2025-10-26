<?php

declare(strict_types=1);

use Farzai\Breaker\Health\HealthReport;
use Farzai\Breaker\Health\HealthStatus;

describe('HealthReport', function () {
    it('can create a health report', function () {
        $report = new HealthReport(
            status: HealthStatus::HEALTHY,
            state: 'closed',
            failureCount: 0,
            successCount: 10,
            failureThreshold: 5,
            successThreshold: 2,
            lastFailureTime: null
        );

        expect($report)->toBeInstanceOf(HealthReport::class);
    });

    it('can create with all properties', function () {
        $report = new HealthReport(
            status: HealthStatus::DEGRADED,
            state: 'half-open',
            failureCount: 3,
            successCount: 1,
            failureThreshold: 5,
            successThreshold: 2,
            lastFailureTime: 1234567890,
            message: 'Service is degraded'
        );

        expect($report->status)->toBe(HealthStatus::DEGRADED)
            ->and($report->state)->toBe('half-open')
            ->and($report->failureCount)->toBe(3)
            ->and($report->successCount)->toBe(1)
            ->and($report->failureThreshold)->toBe(5)
            ->and($report->successThreshold)->toBe(2)
            ->and($report->lastFailureTime)->toBe(1234567890)
            ->and($report->message)->toBe('Service is degraded');
    });

    it('can check if healthy', function () {
        $report = new HealthReport(
            status: HealthStatus::HEALTHY,
            state: 'closed',
            failureCount: 0,
            successCount: 10,
            failureThreshold: 5,
            successThreshold: 2,
            lastFailureTime: null
        );

        expect($report->isHealthy())->toBeTrue()
            ->and($report->isDegraded())->toBeFalse()
            ->and($report->isUnhealthy())->toBeFalse();
    });

    it('can check if degraded', function () {
        $report = new HealthReport(
            status: HealthStatus::DEGRADED,
            state: 'half-open',
            failureCount: 2,
            successCount: 1,
            failureThreshold: 5,
            successThreshold: 2,
            lastFailureTime: 1234567890
        );

        expect($report->isHealthy())->toBeFalse()
            ->and($report->isDegraded())->toBeTrue()
            ->and($report->isUnhealthy())->toBeFalse();
    });

    it('can check if unhealthy', function () {
        $report = new HealthReport(
            status: HealthStatus::UNHEALTHY,
            state: 'open',
            failureCount: 5,
            successCount: 0,
            failureThreshold: 5,
            successThreshold: 2,
            lastFailureTime: 1234567890
        );

        expect($report->isHealthy())->toBeFalse()
            ->and($report->isDegraded())->toBeFalse()
            ->and($report->isUnhealthy())->toBeTrue();
    });

    it('can convert to array', function () {
        $report = new HealthReport(
            status: HealthStatus::HEALTHY,
            state: 'closed',
            failureCount: 1,
            successCount: 10,
            failureThreshold: 5,
            successThreshold: 2,
            lastFailureTime: 1234567890,
            message: 'All good'
        );

        $array = $report->toArray();

        expect($array)->toBeArray()
            ->and($array)->toMatchArray([
                'status' => 'healthy',
                'state' => 'closed',
                'failure_count' => 1,
                'success_count' => 10,
                'failure_threshold' => 5,
                'success_threshold' => 2,
                'last_failure_time' => 1234567890,
                'message' => 'All good',
            ]);
    });

    it('can convert to JSON', function () {
        $report = new HealthReport(
            status: HealthStatus::DEGRADED,
            state: 'half-open',
            failureCount: 2,
            successCount: 1,
            failureThreshold: 5,
            successThreshold: 2,
            lastFailureTime: 1234567890
        );

        $json = $report->toJson();

        expect($json)->toBeString();
        $decoded = json_decode($json, true);
        expect($decoded)->toMatchArray([
            'status' => 'degraded',
            'state' => 'half-open',
            'failure_count' => 2,
        ]);
    });

    it('includes null message in toArray when not set', function () {
        $report = new HealthReport(
            status: HealthStatus::HEALTHY,
            state: 'closed',
            failureCount: 0,
            successCount: 5,
            failureThreshold: 5,
            successThreshold: 2,
            lastFailureTime: null
        );

        $array = $report->toArray();

        expect($array)->toHaveKey('message')
            ->and($array['message'])->toBeNull();
    });

    it('is readonly and cannot be modified', function () {
        $report = new HealthReport(
            status: HealthStatus::HEALTHY,
            state: 'closed',
            failureCount: 0,
            successCount: 5,
            failureThreshold: 5,
            successThreshold: 2,
            lastFailureTime: null
        );

        expect(function () use ($report) {
            $report->failureCount = 10; // @phpstan-ignore-line
        })->toThrow(Error::class);
    });
});

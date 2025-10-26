<?php

declare(strict_types=1);

namespace Farzai\Breaker\Contracts;

use Farzai\Breaker\Metrics\CircuitMetrics;

/**
 * Interface for collecting circuit breaker metrics.
 */
interface MetricsCollectorInterface
{
    /**
     * Record a successful call.
     *
     * @param  int  $timestamp  Current timestamp
     */
    public function recordSuccess(int $timestamp): void;

    /**
     * Record a failed call.
     *
     * @param  int  $timestamp  Current timestamp
     */
    public function recordFailure(int $timestamp): void;

    /**
     * Record a rejected call (circuit open).
     */
    public function recordRejection(): void;

    /**
     * Record a fallback execution.
     */
    public function recordFallback(): void;

    /**
     * Record a state transition.
     *
     * @param  int  $timestamp  Current timestamp
     */
    public function recordStateTransition(int $timestamp): void;

    /**
     * Get current metrics snapshot.
     */
    public function getMetrics(): CircuitMetrics;

    /**
     * Reset all metrics.
     */
    public function reset(): void;
}

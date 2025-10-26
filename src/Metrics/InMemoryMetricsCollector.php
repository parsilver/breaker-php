<?php

declare(strict_types=1);

namespace Farzai\Breaker\Metrics;

use Farzai\Breaker\Contracts\MetricsCollectorInterface;

/**
 * In-memory metrics collector.
 *
 * Stores metrics in memory using immutable value objects.
 */
final class InMemoryMetricsCollector implements MetricsCollectorInterface
{
    private CircuitMetrics $metrics;

    public function __construct()
    {
        $this->metrics = new CircuitMetrics;
    }

    /**
     * {@inheritDoc}
     */
    public function recordSuccess(int $timestamp): void
    {
        $this->metrics = $this->metrics->withSuccess($timestamp);
    }

    /**
     * {@inheritDoc}
     */
    public function recordFailure(int $timestamp): void
    {
        $this->metrics = $this->metrics->withFailure($timestamp);
    }

    /**
     * {@inheritDoc}
     */
    public function recordRejection(): void
    {
        $this->metrics = $this->metrics->withRejection();
    }

    /**
     * {@inheritDoc}
     */
    public function recordFallback(): void
    {
        $this->metrics = $this->metrics->withFallback();
    }

    /**
     * {@inheritDoc}
     */
    public function recordStateTransition(int $timestamp): void
    {
        $this->metrics = $this->metrics->withStateTransition($timestamp);
    }

    /**
     * {@inheritDoc}
     */
    public function getMetrics(): CircuitMetrics
    {
        return $this->metrics;
    }

    /**
     * {@inheritDoc}
     */
    public function reset(): void
    {
        $this->metrics = new CircuitMetrics;
    }
}

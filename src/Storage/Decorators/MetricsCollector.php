<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage\Decorators;

/**
 * Interface for collecting storage metrics.
 *
 * Implementations can push metrics to monitoring systems like:
 * - Prometheus
 * - StatsD
 * - CloudWatch
 * - Custom monitoring solutions
 */
interface MetricsCollector
{
    /**
     * Record a storage operation.
     *
     * @param  string  $operation  Operation name (read, write, delete, etc.)
     * @param  string  $adapter  Adapter name
     * @param  float  $durationMs  Operation duration in milliseconds
     * @param  bool  $success  Whether operation succeeded
     * @param  array<string, mixed>  $tags  Additional tags/labels
     */
    public function recordOperation(
        string $operation,
        string $adapter,
        float $durationMs,
        bool $success,
        array $tags = []
    ): void;

    /**
     * Increment a counter metric.
     *
     * @param  string  $metric  Metric name
     * @param  int  $value  Value to increment by
     * @param  array<string, mixed>  $tags  Additional tags/labels
     */
    public function increment(string $metric, int $value = 1, array $tags = []): void;
}

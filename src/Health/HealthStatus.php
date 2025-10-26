<?php

declare(strict_types=1);

namespace Farzai\Breaker\Health;

/**
 * Health status enumeration.
 */
enum HealthStatus: string
{
    /**
     * Circuit is healthy - closed state, low failure rate.
     */
    case HEALTHY = 'healthy';

    /**
     * Circuit is degraded - half-open state or high failure rate.
     */
    case DEGRADED = 'degraded';

    /**
     * Circuit is unhealthy - open state, failing fast.
     */
    case UNHEALTHY = 'unhealthy';
}

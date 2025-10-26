<?php

declare(strict_types=1);

namespace Farzai\Breaker\Health;

/**
 * Health report value object.
 *
 * Provides comprehensive health information about the circuit breaker.
 */
final readonly class HealthReport
{
    public function __construct(
        public HealthStatus $status,
        public string $state,
        public int $failureCount,
        public int $successCount,
        public int $failureThreshold,
        public int $successThreshold,
        public ?int $lastFailureTime,
        public ?string $message = null,
    ) {}

    /**
     * Check if the circuit is healthy.
     */
    public function isHealthy(): bool
    {
        return $this->status === HealthStatus::HEALTHY;
    }

    /**
     * Check if the circuit is degraded.
     */
    public function isDegraded(): bool
    {
        return $this->status === HealthStatus::DEGRADED;
    }

    /**
     * Check if the circuit is unhealthy.
     */
    public function isUnhealthy(): bool
    {
        return $this->status === HealthStatus::UNHEALTHY;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'failure_threshold' => $this->failureThreshold,
            'success_threshold' => $this->successThreshold,
            'last_failure_time' => $this->lastFailureTime,
            'message' => $this->message,
        ];
    }

    /**
     * Convert to JSON representation.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}

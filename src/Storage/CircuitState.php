<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage;

/**
 * Value object representing the complete state of a circuit breaker.
 *
 * This immutable object encapsulates all the data needed to persist
 * and restore a circuit breaker's state.
 */
readonly class CircuitState
{
    /**
     * Create a new circuit state.
     *
     * @param  string  $serviceKey  Unique identifier for the service
     * @param  string  $state  Current state (open, closed, half-open)
     * @param  int  $failureCount  Number of consecutive failures
     * @param  int  $successCount  Number of consecutive successes
     * @param  int|null  $lastFailureTime  Unix timestamp of last failure
     */
    public function __construct(
        public string $serviceKey,
        public string $state,
        public int $failureCount = 0,
        public int $successCount = 0,
        public ?int $lastFailureTime = null,
    ) {}

    /**
     * Create from array data (for deserialization).
     *
     * @param  string  $serviceKey  Service identifier
     * @param  array<string, mixed>  $data  State data
     */
    public static function fromArray(string $serviceKey, array $data): self
    {
        return new self(
            serviceKey: $serviceKey,
            state: (string) ($data['state'] ?? 'closed'),
            failureCount: (int) ($data['failure_count'] ?? 0),
            successCount: (int) ($data['success_count'] ?? 0),
            lastFailureTime: isset($data['last_failure_time']) && $data['last_failure_time'] > 0
                ? (int) $data['last_failure_time']
                : null,
        );
    }

    /**
     * Convert to array (for serialization).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'last_failure_time' => $this->lastFailureTime ?? 0,
        ];
    }

    /**
     * Create a new instance with updated failure count.
     */
    public function withFailureCount(int $failureCount): self
    {
        return new self(
            serviceKey: $this->serviceKey,
            state: $this->state,
            failureCount: $failureCount,
            successCount: $this->successCount,
            lastFailureTime: $this->lastFailureTime,
        );
    }

    /**
     * Create a new instance with updated success count.
     */
    public function withSuccessCount(int $successCount): self
    {
        return new self(
            serviceKey: $this->serviceKey,
            state: $this->state,
            failureCount: $this->failureCount,
            successCount: $successCount,
            lastFailureTime: $this->lastFailureTime,
        );
    }

    /**
     * Create a new instance with updated state.
     */
    public function withState(string $state): self
    {
        return new self(
            serviceKey: $this->serviceKey,
            state: $state,
            failureCount: $this->failureCount,
            successCount: $this->successCount,
            lastFailureTime: $this->lastFailureTime,
        );
    }

    /**
     * Create a new instance with updated last failure time.
     */
    public function withLastFailureTime(?int $lastFailureTime): self
    {
        return new self(
            serviceKey: $this->serviceKey,
            state: $this->state,
            failureCount: $this->failureCount,
            successCount: $this->successCount,
            lastFailureTime: $lastFailureTime,
        );
    }
}

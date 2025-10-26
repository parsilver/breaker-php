<?php

declare(strict_types=1);

namespace Farzai\Breaker\Metrics;

/**
 * Value object for circuit breaker metrics.
 *
 * Tracks comprehensive statistics about circuit breaker operations.
 */
final readonly class CircuitMetrics
{
    public function __construct(
        public int $totalCalls = 0,
        public int $successfulCalls = 0,
        public int $failedCalls = 0,
        public int $rejectedCalls = 0,
        public int $fallbackCalls = 0,
        public int $stateTransitions = 0,
        public int $timeInClosed = 0,
        public int $timeInOpen = 0,
        public int $timeInHalfOpen = 0,
        public ?int $lastStateChangeTime = null,
        public ?int $lastSuccessTime = null,
        public ?int $lastFailureTime = null,
    ) {}

    /**
     * Get success rate as a percentage.
     *
     * @return float Success rate (0-100)
     */
    public function getSuccessRate(): float
    {
        if ($this->totalCalls === 0) {
            return 0.0;
        }

        return ($this->successfulCalls / $this->totalCalls) * 100;
    }

    /**
     * Get failure rate as a percentage.
     *
     * @return float Failure rate (0-100)
     */
    public function getFailureRate(): float
    {
        if ($this->totalCalls === 0) {
            return 0.0;
        }

        return ($this->failedCalls / $this->totalCalls) * 100;
    }

    /**
     * Get rejection rate as a percentage.
     *
     * @return float Rejection rate (0-100)
     */
    public function getRejectionRate(): float
    {
        if ($this->totalCalls === 0) {
            return 0.0;
        }

        return ($this->rejectedCalls / $this->totalCalls) * 100;
    }

    /**
     * Convert metrics to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_calls' => $this->totalCalls,
            'successful_calls' => $this->successfulCalls,
            'failed_calls' => $this->failedCalls,
            'rejected_calls' => $this->rejectedCalls,
            'fallback_calls' => $this->fallbackCalls,
            'state_transitions' => $this->stateTransitions,
            'time_in_closed' => $this->timeInClosed,
            'time_in_open' => $this->timeInOpen,
            'time_in_half_open' => $this->timeInHalfOpen,
            'success_rate' => round($this->getSuccessRate(), 2),
            'failure_rate' => round($this->getFailureRate(), 2),
            'rejection_rate' => round($this->getRejectionRate(), 2),
            'last_state_change_time' => $this->lastStateChangeTime,
            'last_success_time' => $this->lastSuccessTime,
            'last_failure_time' => $this->lastFailureTime,
        ];
    }

    /**
     * Create a new instance with incremented total calls.
     */
    public function withIncrementedTotalCalls(): self
    {
        return new self(
            totalCalls: $this->totalCalls + 1,
            successfulCalls: $this->successfulCalls,
            failedCalls: $this->failedCalls,
            rejectedCalls: $this->rejectedCalls,
            fallbackCalls: $this->fallbackCalls,
            stateTransitions: $this->stateTransitions,
            timeInClosed: $this->timeInClosed,
            timeInOpen: $this->timeInOpen,
            timeInHalfOpen: $this->timeInHalfOpen,
            lastStateChangeTime: $this->lastStateChangeTime,
            lastSuccessTime: $this->lastSuccessTime,
            lastFailureTime: $this->lastFailureTime,
        );
    }

    /**
     * Create a new instance with incremented successful calls.
     *
     * @param  int  $timestamp  Current timestamp
     */
    public function withSuccess(int $timestamp): self
    {
        return new self(
            totalCalls: $this->totalCalls + 1,
            successfulCalls: $this->successfulCalls + 1,
            failedCalls: $this->failedCalls,
            rejectedCalls: $this->rejectedCalls,
            fallbackCalls: $this->fallbackCalls,
            stateTransitions: $this->stateTransitions,
            timeInClosed: $this->timeInClosed,
            timeInOpen: $this->timeInOpen,
            timeInHalfOpen: $this->timeInHalfOpen,
            lastStateChangeTime: $this->lastStateChangeTime,
            lastSuccessTime: $timestamp,
            lastFailureTime: $this->lastFailureTime,
        );
    }

    /**
     * Create a new instance with incremented failed calls.
     *
     * @param  int  $timestamp  Current timestamp
     */
    public function withFailure(int $timestamp): self
    {
        return new self(
            totalCalls: $this->totalCalls + 1,
            successfulCalls: $this->successfulCalls,
            failedCalls: $this->failedCalls + 1,
            rejectedCalls: $this->rejectedCalls,
            fallbackCalls: $this->fallbackCalls,
            stateTransitions: $this->stateTransitions,
            timeInClosed: $this->timeInClosed,
            timeInOpen: $this->timeInOpen,
            timeInHalfOpen: $this->timeInHalfOpen,
            lastStateChangeTime: $this->lastStateChangeTime,
            lastSuccessTime: $this->lastSuccessTime,
            lastFailureTime: $timestamp,
        );
    }

    /**
     * Create a new instance with incremented rejected calls.
     */
    public function withRejection(): self
    {
        return new self(
            totalCalls: $this->totalCalls + 1,
            successfulCalls: $this->successfulCalls,
            failedCalls: $this->failedCalls,
            rejectedCalls: $this->rejectedCalls + 1,
            fallbackCalls: $this->fallbackCalls,
            stateTransitions: $this->stateTransitions,
            timeInClosed: $this->timeInClosed,
            timeInOpen: $this->timeInOpen,
            timeInHalfOpen: $this->timeInHalfOpen,
            lastStateChangeTime: $this->lastStateChangeTime,
            lastSuccessTime: $this->lastSuccessTime,
            lastFailureTime: $this->lastFailureTime,
        );
    }

    /**
     * Create a new instance with incremented fallback calls.
     */
    public function withFallback(): self
    {
        return new self(
            totalCalls: $this->totalCalls,
            successfulCalls: $this->successfulCalls,
            failedCalls: $this->failedCalls,
            rejectedCalls: $this->rejectedCalls,
            fallbackCalls: $this->fallbackCalls + 1,
            stateTransitions: $this->stateTransitions,
            timeInClosed: $this->timeInClosed,
            timeInOpen: $this->timeInOpen,
            timeInHalfOpen: $this->timeInHalfOpen,
            lastStateChangeTime: $this->lastStateChangeTime,
            lastSuccessTime: $this->lastSuccessTime,
            lastFailureTime: $this->lastFailureTime,
        );
    }

    /**
     * Create a new instance with state transition recorded.
     *
     * @param  int  $timestamp  Current timestamp
     */
    public function withStateTransition(int $timestamp): self
    {
        return new self(
            totalCalls: $this->totalCalls,
            successfulCalls: $this->successfulCalls,
            failedCalls: $this->failedCalls,
            rejectedCalls: $this->rejectedCalls,
            fallbackCalls: $this->fallbackCalls,
            stateTransitions: $this->stateTransitions + 1,
            timeInClosed: $this->timeInClosed,
            timeInOpen: $this->timeInOpen,
            timeInHalfOpen: $this->timeInHalfOpen,
            lastStateChangeTime: $timestamp,
            lastSuccessTime: $this->lastSuccessTime,
            lastFailureTime: $this->lastFailureTime,
        );
    }
}

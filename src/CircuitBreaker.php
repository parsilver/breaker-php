<?php

namespace Farzai\Breaker;

use Farzai\Breaker\States\ClosedState;
use Farzai\Breaker\States\HalfOpenState;
use Farzai\Breaker\States\OpenState;
use Farzai\Breaker\States\StateInterface;
use Farzai\Breaker\Storage\InMemoryStorage;
use Farzai\Breaker\Storage\StorageInterface;

class CircuitBreaker
{
    protected string $serviceKey;

    protected int $failureCount = 0;

    protected int $successCount = 0;

    protected int $lastFailureTime = 0;

    protected StateInterface $state;

    protected int $failureThreshold;

    protected int $successThreshold;

    protected int $timeout;

    protected StorageInterface $storage;

    /**
     * Create a new CircuitBreaker instance.
     */
    public function __construct(
        string $serviceKey,
        array $options = [],
        ?StorageInterface $storage = null
    ) {
        $this->serviceKey = $serviceKey;

        $this->failureThreshold = $options['failure_threshold'] ?? 5;
        $this->successThreshold = $options['success_threshold'] ?? 2;
        $this->timeout = $options['timeout'] ?? 30;

        $this->storage = $storage ?? new InMemoryStorage;

        $this->initializeState();
    }

    /**
     * Execute a protected callable.
     */
    public function call(callable $callable): mixed
    {
        return $this->state->call($this, $callable);
    }

    /**
     * Get the current state name.
     */
    public function getState(): string
    {
        return $this->state->getName();
    }

    /**
     * Transition to closed state.
     */
    public function close(): void
    {
        $this->setState(new ClosedState);
        $this->resetFailureCount();
        $this->resetSuccessCount();
    }

    /**
     * Transition to open state.
     */
    public function open(): void
    {
        $this->setState(new OpenState);
        $this->lastFailureTime = time();
        $this->resetSuccessCount();
    }

    /**
     * Transition to half-open state.
     */
    public function halfOpen(): void
    {
        $this->setState(new HalfOpenState);
        $this->resetSuccessCount();
    }

    /**
     * Get the failure count.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Increment the failure count.
     */
    public function incrementFailureCount(): void
    {
        $this->failureCount++;
        $this->saveState();
    }

    /**
     * Reset the failure count.
     */
    public function resetFailureCount(): void
    {
        $this->failureCount = 0;
        $this->saveState();
    }

    /**
     * Get the success count.
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Increment the success count.
     */
    public function incrementSuccessCount(): void
    {
        $this->successCount++;
        $this->saveState();
    }

    /**
     * Reset the success count.
     */
    public function resetSuccessCount(): void
    {
        $this->successCount = 0;
        $this->saveState();
    }

    /**
     * Get the last failure time.
     */
    public function getLastFailureTime(): int
    {
        return $this->lastFailureTime;
    }

    /**
     * Get the failure threshold.
     */
    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    /**
     * Get the success threshold.
     */
    public function getSuccessThreshold(): int
    {
        return $this->successThreshold;
    }

    /**
     * Get the timeout.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Set the current state.
     */
    protected function setState(StateInterface $state): void
    {
        $this->state = $state;
        $this->saveState();
    }

    /**
     * Initialize the circuit state from storage or default to closed.
     */
    protected function initializeState(): void
    {
        $data = $this->storage->load($this->serviceKey);

        if ($data) {
            $this->failureCount = $data['failure_count'] ?? 0;
            $this->successCount = $data['success_count'] ?? 0;
            $this->lastFailureTime = $data['last_failure_time'] ?? 0;

            $stateName = $data['state'] ?? 'closed';

            $this->state = match ($stateName) {
                'open' => new OpenState,
                'half-open' => new HalfOpenState,
                default => new ClosedState,
            };
        } else {
            $this->state = new ClosedState;
        }
    }

    /**
     * Save the current state to storage.
     */
    protected function saveState(): void
    {
        $this->storage->save($this->serviceKey, [
            'state' => $this->state->getName(),
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'last_failure_time' => $this->lastFailureTime,
        ]);
    }
}

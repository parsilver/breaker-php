<?php

namespace Farzai\Breaker;

use Farzai\Breaker\Events\EventDispatcher;
use Farzai\Breaker\Events\Events;
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
     * Event dispatcher for circuit breaker events.
     */
    protected EventDispatcher $eventDispatcher;

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
        $this->eventDispatcher = new EventDispatcher;

        $this->initializeState();
    }

    /**
     * Execute a protected callable.
     */
    public function call(callable $callable): mixed
    {
        try {
            $result = $this->state->call($this, $callable);

            // Dispatch success event
            $this->eventDispatcher->dispatch(Events::SUCCESS, [$result, $this]);

            return $result;
        } catch (\Throwable $exception) {
            // Dispatch failure event
            $this->eventDispatcher->dispatch(Events::FAILURE, [$exception, $this]);

            throw $exception;
        }
    }

    /**
     * Execute a protected callable with a fallback.
     *
     * @param  callable  $callable  The primary function to execute
     * @param  callable  $fallback  The fallback function to execute if the primary function fails
     * @return mixed The result of either the primary function or the fallback
     */
    public function callWithFallback(callable $callable, callable $fallback): mixed
    {
        try {
            return $this->call($callable);
        } catch (\Throwable $exception) {
            // Pass both the exception and the circuit breaker instance to the fallback
            $fallbackResult = $fallback($exception, $this);

            // Dispatch fallback success event
            $this->eventDispatcher->dispatch(Events::FALLBACK_SUCCESS, [$fallbackResult, $exception, $this]);

            return $fallbackResult;
        }
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
     * Get the service key.
     */
    public function getServiceKey(): string
    {
        return $this->serviceKey;
    }

    /**
     * Add a listener for state changes.
     *
     * @param  callable  $listener  Function that receives ($newState, $oldState, $circuitBreaker)
     * @return int The listener ID
     */
    public function onStateChange(callable $listener): int
    {
        return $this->eventDispatcher->addListener(Events::STATE_CHANGE, $listener);
    }

    /**
     * Add a listener for when the circuit opens.
     *
     * @param  callable  $listener  Function that receives ($circuitBreaker)
     * @return int The listener ID
     */
    public function onOpen(callable $listener): int
    {
        return $this->eventDispatcher->addListener(Events::OPEN, $listener);
    }

    /**
     * Add a listener for when the circuit closes.
     *
     * @param  callable  $listener  Function that receives ($circuitBreaker)
     * @return int The listener ID
     */
    public function onClose(callable $listener): int
    {
        return $this->eventDispatcher->addListener(Events::CLOSE, $listener);
    }

    /**
     * Add a listener for when the circuit transitions to half-open.
     *
     * @param  callable  $listener  Function that receives ($circuitBreaker)
     * @return int The listener ID
     */
    public function onHalfOpen(callable $listener): int
    {
        return $this->eventDispatcher->addListener(Events::HALF_OPEN, $listener);
    }

    /**
     * Add a listener for successful calls.
     *
     * @param  callable  $listener  Function that receives ($result, $circuitBreaker)
     * @return int The listener ID
     */
    public function onSuccess(callable $listener): int
    {
        return $this->eventDispatcher->addListener(Events::SUCCESS, $listener);
    }

    /**
     * Add a listener for failed calls.
     *
     * @param  callable  $listener  Function that receives ($exception, $circuitBreaker)
     * @return int The listener ID
     */
    public function onFailure(callable $listener): int
    {
        return $this->eventDispatcher->addListener(Events::FAILURE, $listener);
    }

    /**
     * Add a listener for successful fallbacks.
     *
     * @param  callable  $listener  Function that receives ($fallbackResult, $exception, $circuitBreaker)
     * @return int The listener ID
     */
    public function onFallbackSuccess(callable $listener): int
    {
        return $this->eventDispatcher->addListener(Events::FALLBACK_SUCCESS, $listener);
    }

    /**
     * Remove a listener by its ID.
     *
     * @param  int  $listenerId  The listener ID to remove
     * @return bool True if the listener was removed, false if it didn't exist
     */
    public function removeListener(int $listenerId): bool
    {
        return $this->eventDispatcher->removeListener($listenerId);
    }

    /**
     * Set the current state.
     */
    protected function setState(StateInterface $state): void
    {
        $oldState = isset($this->state) ? $this->state->getName() : 'none';
        $newState = $state->getName();

        $this->state = $state;
        $this->saveState();

        // Don't dispatch events if this is the initial state
        if ($oldState === 'none') {
            return;
        }

        // Dispatch general state change event
        $this->eventDispatcher->dispatch(Events::STATE_CHANGE, [$newState, $oldState, $this]);

        // Dispatch specific state transition event
        switch ($newState) {
            case 'open':
                $this->eventDispatcher->dispatch(Events::OPEN, [$this]);
                break;
            case 'closed':
                $this->eventDispatcher->dispatch(Events::CLOSE, [$this]);
                break;
            case 'half-open':
                $this->eventDispatcher->dispatch(Events::HALF_OPEN, [$this]);
                break;
        }
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

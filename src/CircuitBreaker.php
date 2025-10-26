<?php

declare(strict_types=1);

namespace Farzai\Breaker;

use Farzai\Breaker\Config\CircuitBreakerConfig;
use Farzai\Breaker\Contracts\TimeProviderInterface;
use Farzai\Breaker\Events\CallFailedEvent;
use Farzai\Breaker\Events\CallSucceededEvent;
use Farzai\Breaker\Events\CircuitClosedEvent;
use Farzai\Breaker\Events\CircuitHalfOpenedEvent;
use Farzai\Breaker\Events\CircuitOpenedEvent;
use Farzai\Breaker\Events\CircuitStateChangedEvent;
use Farzai\Breaker\Events\EventDispatcher;
use Farzai\Breaker\Events\EventSubscriberInterface;
use Farzai\Breaker\Events\FallbackExecutedEvent;
use Farzai\Breaker\Health\HealthReport;
use Farzai\Breaker\Health\HealthStatus;
use Farzai\Breaker\States\ClosedState;
use Farzai\Breaker\States\HalfOpenState;
use Farzai\Breaker\States\OpenState;
use Farzai\Breaker\States\StateInterface;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\CircuitStateRepository;
use Farzai\Breaker\Storage\DefaultCircuitStateRepository;
use Farzai\Breaker\Storage\JsonStorageSerializer;
use Farzai\Breaker\Time\SystemTimeProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

    protected CircuitStateRepository $repository;

    /**
     * Event dispatcher for circuit breaker events.
     */
    protected EventDispatcher $eventDispatcher;

    /**
     * PSR-3 Logger instance.
     */
    protected LoggerInterface $logger;

    /**
     * Time provider for testable time operations.
     */
    protected TimeProviderInterface $timeProvider;

    /**
     * Create a new CircuitBreaker instance.
     *
     * @param  string  $serviceKey  Unique identifier for the service
     * @param  CircuitBreakerConfig|array<string, mixed>  $config  Configuration object or array
     * @param  CircuitStateRepository|null  $repository  State repository
     * @param  LoggerInterface|null  $logger  PSR-3 logger
     * @param  TimeProviderInterface|null  $timeProvider  Time provider for testability
     */
    public function __construct(
        string $serviceKey,
        CircuitBreakerConfig|array $config = [],
        ?CircuitStateRepository $repository = null,
        ?LoggerInterface $logger = null,
        ?TimeProviderInterface $timeProvider = null
    ) {
        $this->serviceKey = $serviceKey;

        // Support both Config object and array
        if ($config instanceof CircuitBreakerConfig) {
            $this->failureThreshold = $config->failureThreshold;
            $this->successThreshold = $config->successThreshold;
            $this->timeout = $config->timeout;
        } else {
            $this->failureThreshold = $config['failure_threshold'] ?? 5;
            $this->successThreshold = $config['success_threshold'] ?? 2;
            $this->timeout = $config['timeout'] ?? 30;
        }

        // Use provided repository or create default in-memory one
        $this->repository = $repository ?? new DefaultCircuitStateRepository(
            new InMemoryStorageAdapter,
            new JsonStorageSerializer
        );

        $this->eventDispatcher = new EventDispatcher;
        $this->logger = $logger ?? new NullLogger;
        $this->timeProvider = $timeProvider ?? new SystemTimeProvider;

        $this->initializeState();
    }

    /**
     * Execute a protected callable.
     */
    public function call(callable $callable): mixed
    {
        $startTime = microtime(true);

        try {
            $result = $this->state->call($this, $callable);

            // Calculate execution time in milliseconds
            $executionTime = (microtime(true) - $startTime) * 1000;

            // Dispatch success event with new event object
            $event = new CallSucceededEvent(
                circuitBreaker: $this,
                result: $result,
                executionTime: $executionTime,
                timestamp: time()
            );
            $this->eventDispatcher->dispatch($event);

            return $result;
        } catch (\Throwable $exception) {
            // Calculate execution time in milliseconds
            $executionTime = (microtime(true) - $startTime) * 1000;

            // Dispatch failure event with new event object
            $event = new CallFailedEvent(
                circuitBreaker: $this,
                exception: $exception,
                executionTime: $executionTime,
                timestamp: time()
            );
            $this->eventDispatcher->dispatch($event);

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
            $startTime = microtime(true);

            // Pass both the exception and the circuit breaker instance to the fallback
            $fallbackResult = $fallback($exception, $this);

            // Calculate execution time in milliseconds
            $executionTime = (microtime(true) - $startTime) * 1000;

            // Dispatch fallback success event with new event object
            $event = new FallbackExecutedEvent(
                circuitBreaker: $this,
                result: $fallbackResult,
                originalException: $exception,
                executionTime: $executionTime,
                timestamp: time()
            );
            $this->eventDispatcher->dispatch($event);

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
        $this->lastFailureTime = $this->timeProvider->getCurrentTime();
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
     * Get the time provider.
     */
    public function getTimeProvider(): TimeProviderInterface
    {
        return $this->timeProvider;
    }

    /**
     * Get health status report.
     */
    public function getHealth(): HealthReport
    {
        $state = $this->getState();

        // Determine health status based on circuit state
        $status = match ($state) {
            'closed' => $this->failureCount > ($this->failureThreshold / 2)
                ? HealthStatus::DEGRADED
                : HealthStatus::HEALTHY,
            'half-open' => HealthStatus::DEGRADED,
            'open' => HealthStatus::UNHEALTHY,
            default => HealthStatus::HEALTHY,
        };

        // Generate appropriate message
        $message = match ($status) {
            HealthStatus::HEALTHY => 'Circuit is healthy and operating normally',
            HealthStatus::DEGRADED => $state === 'half-open'
                ? 'Circuit is testing if service has recovered'
                : "Circuit has {$this->failureCount} failures (threshold: {$this->failureThreshold})",
            HealthStatus::UNHEALTHY => 'Circuit is open, failing fast to protect system',
        };

        return new HealthReport(
            status: $status,
            state: $state,
            failureCount: $this->failureCount,
            successCount: $this->successCount,
            failureThreshold: $this->failureThreshold,
            successThreshold: $this->successThreshold,
            lastFailureTime: $this->lastFailureTime > 0 ? $this->lastFailureTime : null,
            message: $message,
        );
    }

    /**
     * Add a listener for state changes.
     *
     * @param  callable  $listener  Function that receives (CircuitStateChangedEvent $event)
     * @param  int  $priority  Listener priority (higher = earlier execution)
     * @return int The listener ID
     */
    public function onStateChange(callable $listener, int $priority = 0): int
    {
        return $this->eventDispatcher->addListener(CircuitStateChangedEvent::class, $listener, $priority);
    }

    /**
     * Add a listener for when the circuit opens.
     *
     * @param  callable  $listener  Function that receives (CircuitOpenedEvent $event)
     * @param  int  $priority  Listener priority (higher = earlier execution)
     * @return int The listener ID
     */
    public function onOpen(callable $listener, int $priority = 0): int
    {
        return $this->eventDispatcher->addListener(CircuitOpenedEvent::class, $listener, $priority);
    }

    /**
     * Add a listener for when the circuit closes.
     *
     * @param  callable  $listener  Function that receives (CircuitClosedEvent $event)
     * @param  int  $priority  Listener priority (higher = earlier execution)
     * @return int The listener ID
     */
    public function onClose(callable $listener, int $priority = 0): int
    {
        return $this->eventDispatcher->addListener(CircuitClosedEvent::class, $listener, $priority);
    }

    /**
     * Add a listener for when the circuit transitions to half-open.
     *
     * @param  callable  $listener  Function that receives (CircuitHalfOpenedEvent $event)
     * @param  int  $priority  Listener priority (higher = earlier execution)
     * @return int The listener ID
     */
    public function onHalfOpen(callable $listener, int $priority = 0): int
    {
        return $this->eventDispatcher->addListener(CircuitHalfOpenedEvent::class, $listener, $priority);
    }

    /**
     * Add a listener for successful calls.
     *
     * @param  callable  $listener  Function that receives (CallSucceededEvent $event)
     * @param  int  $priority  Listener priority (higher = earlier execution)
     * @return int The listener ID
     */
    public function onSuccess(callable $listener, int $priority = 0): int
    {
        return $this->eventDispatcher->addListener(CallSucceededEvent::class, $listener, $priority);
    }

    /**
     * Add a listener for failed calls.
     *
     * @param  callable  $listener  Function that receives (CallFailedEvent $event)
     * @param  int  $priority  Listener priority (higher = earlier execution)
     * @return int The listener ID
     */
    public function onFailure(callable $listener, int $priority = 0): int
    {
        return $this->eventDispatcher->addListener(CallFailedEvent::class, $listener, $priority);
    }

    /**
     * Add a listener for successful fallbacks.
     *
     * @param  callable  $listener  Function that receives (FallbackExecutedEvent $event)
     * @param  int  $priority  Listener priority (higher = earlier execution)
     * @return int The listener ID
     */
    public function onFallbackSuccess(callable $listener, int $priority = 0): int
    {
        return $this->eventDispatcher->addListener(FallbackExecutedEvent::class, $listener, $priority);
    }

    /**
     * Add an event subscriber.
     *
     * Event subscribers can listen to multiple events in a single class.
     *
     * @param  EventSubscriberInterface  $subscriber  The event subscriber
     * @return array<int> Array of listener IDs
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): array
    {
        $listenerIds = [];
        $events = $subscriber::getSubscribedEvents();

        foreach ($events as $eventClass => $params) {
            // Handle different formats
            if (is_string($params)) {
                // Simple format: EventClass::class => 'methodName'
                $method = $params;
                $priority = 0;
            } elseif (is_array($params) && isset($params['method']) && is_string($params['method'])) {
                // Array format: EventClass::class => ['method' => 'methodName', 'priority' => 10]
                $method = $params['method'];
                $priority = is_int($params['priority'] ?? 0) ? $params['priority'] : 0;
            } elseif (is_array($params) && isset($params[0]) && is_array($params[0])) {
                // Multiple listeners: EventClass::class => [['method' => 'method1'], ['method' => 'method2']]
                foreach ($params as $listener) {
                    if (! is_array($listener) || ! isset($listener['method']) || ! is_string($listener['method'])) {
                        continue;
                    }
                    $method = $listener['method'];
                    $priority = is_int($listener['priority'] ?? 0) ? $listener['priority'] : 0;
                    /** @var callable $callable */
                    $callable = [$subscriber, $method];
                    $listenerIds[] = $this->eventDispatcher->addListener(
                        $eventClass,
                        $callable,
                        $priority
                    );
                }

                continue;
            } else {
                continue;
            }

            /** @var callable $callable */
            $callable = [$subscriber, $method];
            $listenerIds[] = $this->eventDispatcher->addListener(
                $eventClass,
                $callable,
                $priority
            );
        }

        return $listenerIds;
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
        // Get old state name - will be 'none' on first call (before state is initialized)
        try {
            $oldState = $this->state->getName();
        } catch (\Error) {
            // State not yet initialized
            $oldState = 'none';
        }

        $newState = $state->getName();

        $this->state = $state;
        $this->saveState();

        // Don't dispatch events if this is the initial state
        if ($oldState === 'none') {
            return;
        }

        $timestamp = time();

        // Dispatch general state change event with new event object
        $stateChangeEvent = new CircuitStateChangedEvent(
            circuitBreaker: $this,
            previousState: $oldState,
            newState: $newState,
            timestamp: $timestamp
        );
        $this->eventDispatcher->dispatch($stateChangeEvent);

        // Dispatch specific state transition event with new event objects
        switch ($newState) {
            case 'open':
                $event = new CircuitOpenedEvent(
                    circuitBreaker: $this,
                    failureCount: $this->failureCount,
                    failureThreshold: $this->failureThreshold,
                    timeout: $this->timeout,
                    timestamp: $timestamp
                );
                $this->eventDispatcher->dispatch($event);
                break;
            case 'closed':
                $event = new CircuitClosedEvent(
                    circuitBreaker: $this,
                    previousState: $oldState,
                    timestamp: $timestamp
                );
                $this->eventDispatcher->dispatch($event);
                break;
            case 'half-open':
                $event = new CircuitHalfOpenedEvent(
                    circuitBreaker: $this,
                    successThreshold: $this->successThreshold,
                    timestamp: $timestamp
                );
                $this->eventDispatcher->dispatch($event);
                break;
        }
    }

    /**
     * Initialize the circuit state from repository or default to closed.
     */
    protected function initializeState(): void
    {
        $circuitState = $this->repository->find($this->serviceKey);

        if ($circuitState) {
            $this->failureCount = $circuitState->failureCount;
            $this->successCount = $circuitState->successCount;
            $this->lastFailureTime = $circuitState->lastFailureTime ?? 0;

            $this->state = match ($circuitState->state) {
                'open' => new OpenState,
                'half-open' => new HalfOpenState,
                default => new ClosedState,
            };
        } else {
            $this->state = new ClosedState;
        }
    }

    /**
     * Save the current state to repository.
     */
    protected function saveState(): void
    {
        $circuitState = new \Farzai\Breaker\Storage\CircuitState(
            serviceKey: $this->serviceKey,
            state: $this->state->getName(),
            failureCount: $this->failureCount,
            successCount: $this->successCount,
            lastFailureTime: $this->lastFailureTime > 0 ? $this->lastFailureTime : null,
        );

        $this->repository->save($circuitState);
    }
}

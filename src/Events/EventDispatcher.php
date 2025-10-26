<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * PSR-14 compliant event dispatcher with exception handling.
 *
 * Features:
 * - PSR-14 compliant event dispatching
 * - Exception-safe listener execution
 * - Configurable error handling strategies
 * - Event propagation control (stoppable events)
 * - PSR-3 logging integration
 */
final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * Error handling strategy constants.
     */
    public const ERROR_STRATEGY_SILENT = 'silent';    // Log and continue

    public const ERROR_STRATEGY_COLLECT = 'collect';  // Collect errors for later

    public const ERROR_STRATEGY_STOP = 'stop';        // Stop on first error

    /**
     * Current error handling strategy.
     */
    private string $errorStrategy = self::ERROR_STRATEGY_SILENT;

    /**
     * PSR-3 logger for error reporting.
     */
    private LoggerInterface $logger;

    /**
     * Listener provider for event listeners.
     */
    private ListenerProviderInterface $listenerProvider;

    /**
     * Collected errors during event dispatching (when using 'collect' strategy).
     *
     * @var array<int, array{event: object, listener: callable, exception: Throwable}>
     */
    private array $dispatchErrors = [];

    /**
     * Create a new event dispatcher.
     *
     * @param  ListenerProviderInterface|null  $listenerProvider  The listener provider
     * @param  LoggerInterface|null  $logger  PSR-3 logger
     */
    public function __construct(
        ?ListenerProviderInterface $listenerProvider = null,
        ?LoggerInterface $logger = null
    ) {
        $this->listenerProvider = $listenerProvider ?? new PrioritizedListenerProvider;
        $this->logger = $logger ?? new NullLogger;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(object $event): object
    {
        $listeners = $this->listenerProvider->getListenersForEvent($event);

        foreach ($listeners as $listener) {
            // Stop propagation if event supports it and propagation is stopped
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }

            try {
                $listener($event);
            } catch (Throwable $exception) {
                $this->handleListenerException($event, $listener, $exception);

                // Stop on first error if strategy is 'stop'
                if ($this->errorStrategy === self::ERROR_STRATEGY_STOP) {
                    break;
                }
            }
        }

        // If we're collecting errors and have some, optionally throw
        if ($this->errorStrategy === self::ERROR_STRATEGY_COLLECT && ! empty($this->dispatchErrors)) {
            // For now, we just collect them - user can retrieve via getDispatchErrors()
            // In future, could add a flag to throw an aggregated exception
        }

        return $event;
    }

    /**
     * Handle an exception thrown by a listener.
     *
     * @param  object  $event  The event that was being dispatched
     * @param  callable  $listener  The listener that threw the exception
     * @param  Throwable  $exception  The exception that was thrown
     */
    private function handleListenerException(object $event, callable $listener, Throwable $exception): void
    {
        // Log the error
        $this->logger->error('Event listener threw an exception', [
            'event' => $event::class,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Store error if collecting
        if ($this->errorStrategy === self::ERROR_STRATEGY_COLLECT) {
            $this->dispatchErrors[] = [
                'event' => $event,
                'listener' => $listener,
                'exception' => $exception,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setErrorHandlingStrategy(string $strategy): void
    {
        if (! in_array($strategy, [self::ERROR_STRATEGY_SILENT, self::ERROR_STRATEGY_COLLECT, self::ERROR_STRATEGY_STOP], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid error handling strategy "%s". Must be one of: %s, %s, %s',
                $strategy,
                self::ERROR_STRATEGY_SILENT,
                self::ERROR_STRATEGY_COLLECT,
                self::ERROR_STRATEGY_STOP
            ));
        }

        $this->errorStrategy = $strategy;
    }

    /**
     * {@inheritDoc}
     */
    public function getErrorHandlingStrategy(): string
    {
        return $this->errorStrategy;
    }

    /**
     * {@inheritDoc}
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function getDispatchErrors(): array
    {
        return $this->dispatchErrors;
    }

    /**
     * {@inheritDoc}
     */
    public function clearDispatchErrors(): void
    {
        $this->dispatchErrors = [];
    }

    /**
     * Get the listener provider.
     */
    public function getListenerProvider(): ListenerProviderInterface
    {
        return $this->listenerProvider;
    }

    /**
     * Add a listener for an event (convenience method).
     *
     * @param  string  $eventClass  The fully qualified event class name
     * @param  callable  $listener  The listener callback
     * @param  int  $priority  The listener priority (higher = earlier execution)
     * @return int The listener ID
     */
    public function addListener(string $eventClass, callable $listener, int $priority = 0): int
    {
        return $this->listenerProvider->addListener($eventClass, $listener, $priority);
    }

    /**
     * Remove a listener by its ID (convenience method).
     *
     * @param  int  $listenerId  The listener ID to remove
     * @return bool True if the listener was removed
     */
    public function removeListener(int $listenerId): bool
    {
        return $this->listenerProvider->removeListener($listenerId);
    }

    /**
     * Check if an event has listeners (convenience method).
     *
     * @param  string  $eventClass  The event class name
     * @return bool True if the event has listeners
     */
    public function hasListeners(string $eventClass): bool
    {
        return $this->listenerProvider->hasListeners($eventClass);
    }

    /**
     * Get the number of listeners for an event (convenience method).
     *
     * @param  string  $eventClass  The event class name
     * @return int The number of listeners
     */
    public function getListenerCount(string $eventClass): int
    {
        return $this->listenerProvider->getListenerCount($eventClass);
    }

    /**
     * Clear all listeners (convenience method).
     *
     * @param  string|null  $eventClass  The event class name (or null for all)
     */
    public function clearListeners(?string $eventClass = null): void
    {
        $this->listenerProvider->clearListeners($eventClass);
    }
}

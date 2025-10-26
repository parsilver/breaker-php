<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

/**
 * Listener provider with priority support.
 *
 * Listeners are executed in order of priority (highest first).
 * Listeners with the same priority execute in registration order.
 */
final class PrioritizedListenerProvider implements ListenerProviderInterface
{
    /**
     * Registered listeners grouped by event class.
     *
     * Structure: [eventClass => [priority => [listenerId => listener]]]
     *
     * @var array<string, array<int, array<int, callable>>>
     */
    private array $listeners = [];

    /**
     * Reverse mapping of listener IDs to event classes for efficient removal.
     *
     * Structure: [listenerId => [eventClass, priority]]
     *
     * @var array<int, array{0: string, 1: int}>
     */
    private array $listenerMap = [];

    /**
     * The next listener ID to assign.
     */
    private int $nextListenerId = 1;

    /**
     * Cached sorted listeners to avoid re-sorting on every dispatch.
     *
     * @var array<string, array<callable>>
     */
    private array $sortedCache = [];

    /**
     * {@inheritDoc}
     */
    public function addListener(string $eventClass, callable $listener, int $priority = 0): int
    {
        $listenerId = $this->nextListenerId++;

        // Initialize event class array if not exists
        if (! isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }

        // Initialize priority array if not exists
        if (! isset($this->listeners[$eventClass][$priority])) {
            $this->listeners[$eventClass][$priority] = [];
        }

        // Add listener
        $this->listeners[$eventClass][$priority][$listenerId] = $listener;

        // Add to reverse map for efficient removal
        $this->listenerMap[$listenerId] = [$eventClass, $priority];

        // Invalidate cache for this event class
        unset($this->sortedCache[$eventClass]);

        return $listenerId;
    }

    /**
     * {@inheritDoc}
     */
    public function removeListener(int $listenerId): bool
    {
        // Check if listener exists in the map
        if (! isset($this->listenerMap[$listenerId])) {
            return false;
        }

        [$eventClass, $priority] = $this->listenerMap[$listenerId];

        // Remove from listeners array
        if (isset($this->listeners[$eventClass][$priority][$listenerId])) {
            unset($this->listeners[$eventClass][$priority][$listenerId]);

            // Clean up empty priority array
            if (empty($this->listeners[$eventClass][$priority])) {
                unset($this->listeners[$eventClass][$priority]);
            }

            // Clean up empty event class array
            if (empty($this->listeners[$eventClass])) {
                unset($this->listeners[$eventClass]);
            }
        }

        // Remove from reverse map
        unset($this->listenerMap[$listenerId]);

        // Invalidate cache for this event class
        unset($this->sortedCache[$eventClass]);

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventClass = $event::class;

        // Return cached sorted listeners if available
        if (isset($this->sortedCache[$eventClass])) {
            return $this->sortedCache[$eventClass];
        }

        // No listeners for this event
        if (! isset($this->listeners[$eventClass])) {
            $this->sortedCache[$eventClass] = [];

            return [];
        }

        // Sort priorities in descending order (highest priority first)
        $priorities = array_keys($this->listeners[$eventClass]);
        rsort($priorities, SORT_NUMERIC);

        // Build flat array of listeners in priority order
        $sortedListeners = [];
        foreach ($priorities as $priority) {
            foreach ($this->listeners[$eventClass][$priority] as $listener) {
                $sortedListeners[] = $listener;
            }
        }

        // Cache the sorted listeners
        $this->sortedCache[$eventClass] = $sortedListeners;

        return $sortedListeners;
    }

    /**
     * {@inheritDoc}
     */
    public function hasListeners(string $eventClass): bool
    {
        return isset($this->listeners[$eventClass]) && ! empty($this->listeners[$eventClass]);
    }

    /**
     * {@inheritDoc}
     */
    public function getListenerCount(string $eventClass): int
    {
        if (! isset($this->listeners[$eventClass])) {
            return 0;
        }

        $count = 0;
        foreach ($this->listeners[$eventClass] as $priorityListeners) {
            $count += count($priorityListeners);
        }

        return $count;
    }

    /**
     * {@inheritDoc}
     */
    public function clearListeners(?string $eventClass = null): void
    {
        if ($eventClass === null) {
            // Clear all listeners
            $this->listeners = [];
            $this->listenerMap = [];
            $this->sortedCache = [];
        } elseif (isset($this->listeners[$eventClass])) {
            // Clear listeners for specific event class
            // First remove from reverse map
            foreach ($this->listeners[$eventClass] as $priorityListeners) {
                foreach (array_keys($priorityListeners) as $listenerId) {
                    unset($this->listenerMap[$listenerId]);
                }
            }

            // Then remove from listeners array
            unset($this->listeners[$eventClass]);
            unset($this->sortedCache[$eventClass]);
        }
    }
}

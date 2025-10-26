<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

/**
 * Provides listeners for events.
 *
 * This interface allows for flexible listener registration and retrieval.
 */
interface ListenerProviderInterface extends \Psr\EventDispatcher\ListenerProviderInterface
{
    /**
     * Add a listener for an event.
     *
     * @param  string  $eventClass  The fully qualified event class name
     * @param  callable  $listener  The listener callback
     * @param  int  $priority  The listener priority (higher = earlier execution)
     * @return int The listener ID (useful for removal)
     */
    public function addListener(string $eventClass, callable $listener, int $priority = 0): int;

    /**
     * Remove a listener by its ID.
     *
     * @param  int  $listenerId  The listener ID to remove
     * @return bool True if the listener was removed, false if it didn't exist
     */
    public function removeListener(int $listenerId): bool;

    /**
     * Check if an event has listeners.
     *
     * @param  string  $eventClass  The event class name
     * @return bool True if the event has listeners, false otherwise
     */
    public function hasListeners(string $eventClass): bool;

    /**
     * Get the number of listeners for an event.
     *
     * @param  string  $eventClass  The event class name
     * @return int The number of listeners
     */
    public function getListenerCount(string $eventClass): int;

    /**
     * Clear all listeners for a specific event or all events.
     *
     * @param  string|null  $eventClass  The event class name (or null for all events)
     */
    public function clearListeners(?string $eventClass = null): void;
}

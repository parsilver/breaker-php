<?php

namespace Farzai\Breaker\Events;

class EventDispatcher
{
    /**
     * Array of registered event listeners.
     */
    protected array $listeners = [];

    /**
     * The next listener ID to assign.
     */
    protected int $nextListenerId = 1;

    /**
     * Add a listener for an event.
     *
     * @param  string  $event  The event name
     * @param  callable  $listener  The listener callback
     * @return int The listener ID (useful for removing the listener later)
     */
    public function addListener(string $event, callable $listener): int
    {
        $listenerId = $this->nextListenerId++;

        $this->listeners[$event][$listenerId] = $listener;

        return $listenerId;
    }

    /**
     * Remove a listener by its ID.
     *
     * @param  int  $listenerId  The listener ID to remove
     * @return bool True if the listener was removed, false if it didn't exist
     */
    public function removeListener(int $listenerId): bool
    {
        foreach ($this->listeners as $event => $listeners) {
            if (isset($listeners[$listenerId])) {
                unset($this->listeners[$event][$listenerId]);

                return true;
            }
        }

        return false;
    }

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param  string  $event  The event name
     * @param  array  $arguments  The arguments to pass to the listeners
     */
    public function dispatch(string $event, array $arguments = []): void
    {
        if (! isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $listener) {
            call_user_func_array($listener, $arguments);
        }
    }

    /**
     * Check if an event has listeners.
     *
     * @param  string  $event  The event name
     * @return bool True if the event has listeners, false otherwise
     */
    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event]) && ! empty($this->listeners[$event]);
    }

    /**
     * Get the number of listeners for an event.
     *
     * @param  string  $event  The event name
     * @return int The number of listeners
     */
    public function getListenerCount(string $event): int
    {
        if (! isset($this->listeners[$event])) {
            return 0;
        }

        return count($this->listeners[$event]);
    }

    /**
     * Clear all listeners for a specific event or all events.
     *
     * @param  string|null  $event  The event name (or null for all events)
     */
    public function clearListeners(?string $event = null): void
    {
        if ($event === null) {
            $this->listeners = [];
        } elseif (isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
    }
}

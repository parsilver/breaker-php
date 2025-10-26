<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

/**
 * Interface for event subscribers.
 *
 * Event subscribers provide a way to organize multiple event listeners
 * in a single class. This is useful for grouping related listeners together.
 */
interface EventSubscriberInterface
{
    /**
     * Get the events this subscriber listens to.
     *
     * Returns an array where keys are event class names and values are:
     * - The method name to call (string)
     * - An array with method name and priority: ['method' => 'onEvent', 'priority' => 10]
     * - An array of arrays for multiple listeners per event
     *
     * Examples:
     * ```php
     * return [
     *     CircuitOpenedEvent::class => 'onCircuitOpened',
     *     CircuitClosedEvent::class => ['method' => 'onCircuitClosed', 'priority' => 100],
     *     CallFailedEvent::class => [
     *         ['method' => 'onCallFailed', 'priority' => 10],
     *         ['method' => 'logFailure', 'priority' => -10],
     *     ],
     * ];
     * ```
     *
     * @return array<string, string|array<string, mixed>|array<int, array<string, mixed>>>
     */
    public static function getSubscribedEvents(): array;
}

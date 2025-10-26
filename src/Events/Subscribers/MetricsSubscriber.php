<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events\Subscribers;

use Farzai\Breaker\Contracts\MetricsCollectorInterface;
use Farzai\Breaker\Events\CallFailedEvent;
use Farzai\Breaker\Events\CallSucceededEvent;
use Farzai\Breaker\Events\CircuitClosedEvent;
use Farzai\Breaker\Events\CircuitHalfOpenedEvent;
use Farzai\Breaker\Events\CircuitOpenedEvent;
use Farzai\Breaker\Events\EventSubscriberInterface;
use Farzai\Breaker\Events\FallbackExecutedEvent;

/**
 * Built-in subscriber for collecting circuit breaker metrics.
 *
 * Automatically records metrics for all circuit breaker events.
 */
final class MetricsSubscriber implements EventSubscriberInterface
{
    /**
     * Create a new metrics subscriber.
     *
     * @param  MetricsCollectorInterface  $metricsCollector  The metrics collector to use
     */
    public function __construct(
        private readonly MetricsCollectorInterface $metricsCollector
    ) {}

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CallSucceededEvent::class => ['method' => 'onCallSucceeded', 'priority' => 100],
            CallFailedEvent::class => ['method' => 'onCallFailed', 'priority' => 100],
            FallbackExecutedEvent::class => ['method' => 'onFallbackExecuted', 'priority' => 100],
            CircuitOpenedEvent::class => ['method' => 'onCircuitStateChanged', 'priority' => 100],
            CircuitClosedEvent::class => ['method' => 'onCircuitStateChanged', 'priority' => 100],
            CircuitHalfOpenedEvent::class => ['method' => 'onCircuitStateChanged', 'priority' => 100],
        ];
    }

    /**
     * Record successful call metrics.
     */
    public function onCallSucceeded(CallSucceededEvent $event): void
    {
        $this->metricsCollector->recordSuccess($event->getTimestamp());
    }

    /**
     * Record failed call metrics.
     */
    public function onCallFailed(CallFailedEvent $event): void
    {
        $this->metricsCollector->recordFailure($event->getTimestamp());
    }

    /**
     * Record fallback execution metrics.
     */
    public function onFallbackExecuted(FallbackExecutedEvent $event): void
    {
        $this->metricsCollector->recordFallback();
    }

    /**
     * Record state transition metrics.
     */
    public function onCircuitStateChanged(CircuitOpenedEvent|CircuitClosedEvent|CircuitHalfOpenedEvent $event): void
    {
        $this->metricsCollector->recordStateTransition($event->getTimestamp());
    }
}

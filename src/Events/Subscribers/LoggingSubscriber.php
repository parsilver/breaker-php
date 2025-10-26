<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events\Subscribers;

use Farzai\Breaker\Events\CallFailedEvent;
use Farzai\Breaker\Events\CallSucceededEvent;
use Farzai\Breaker\Events\CircuitClosedEvent;
use Farzai\Breaker\Events\CircuitHalfOpenedEvent;
use Farzai\Breaker\Events\CircuitOpenedEvent;
use Farzai\Breaker\Events\CircuitStateChangedEvent;
use Farzai\Breaker\Events\EventSubscriberInterface;
use Farzai\Breaker\Events\FallbackExecutedEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Built-in subscriber for logging circuit breaker events.
 *
 * Automatically logs all circuit breaker events using PSR-3 logger.
 */
final class LoggingSubscriber implements EventSubscriberInterface
{
    /**
     * Create a new logging subscriber.
     *
     * @param  LoggerInterface  $logger  The PSR-3 logger to use
     * @param  string  $logLevel  The default log level to use
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $logLevel = LogLevel::INFO
    ) {}

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CircuitStateChangedEvent::class => ['method' => 'onStateChanged', 'priority' => 50],
            CircuitOpenedEvent::class => ['method' => 'onCircuitOpened', 'priority' => 50],
            CircuitClosedEvent::class => ['method' => 'onCircuitClosed', 'priority' => 50],
            CircuitHalfOpenedEvent::class => ['method' => 'onCircuitHalfOpened', 'priority' => 50],
            CallSucceededEvent::class => ['method' => 'onCallSucceeded', 'priority' => 50],
            CallFailedEvent::class => ['method' => 'onCallFailed', 'priority' => 50],
            FallbackExecutedEvent::class => ['method' => 'onFallbackExecuted', 'priority' => 50],
        ];
    }

    /**
     * Log state change events.
     */
    public function onStateChanged(CircuitStateChangedEvent $event): void
    {
        $this->logger->log($this->logLevel, 'Circuit breaker state changed', [
            'service' => $event->getServiceKey(),
            'previous_state' => $event->getPreviousState(),
            'new_state' => $event->getNewState(),
            'timestamp' => $event->getTimestamp(),
        ]);
    }

    /**
     * Log circuit opened events.
     */
    public function onCircuitOpened(CircuitOpenedEvent $event): void
    {
        $this->logger->warning('Circuit breaker opened - service experiencing failures', [
            'service' => $event->getServiceKey(),
            'failure_count' => $event->getFailureCount(),
            'failure_threshold' => $event->getFailureThreshold(),
            'timeout' => $event->getTimeout(),
            'half_open_at' => date('Y-m-d H:i:s', $event->getHalfOpenTimestamp()),
            'timestamp' => $event->getTimestamp(),
        ]);
    }

    /**
     * Log circuit closed events.
     */
    public function onCircuitClosed(CircuitClosedEvent $event): void
    {
        $message = $event->isRecovery()
            ? 'Circuit breaker closed - service recovered'
            : 'Circuit breaker closed';

        $this->logger->log($this->logLevel, $message, [
            'service' => $event->getServiceKey(),
            'previous_state' => $event->getPreviousState(),
            'timestamp' => $event->getTimestamp(),
        ]);
    }

    /**
     * Log circuit half-opened events.
     */
    public function onCircuitHalfOpened(CircuitHalfOpenedEvent $event): void
    {
        $this->logger->log($this->logLevel, 'Circuit breaker half-opened - testing service recovery', [
            'service' => $event->getServiceKey(),
            'success_threshold' => $event->getSuccessThreshold(),
            'timestamp' => $event->getTimestamp(),
        ]);
    }

    /**
     * Log successful call events.
     */
    public function onCallSucceeded(CallSucceededEvent $event): void
    {
        $this->logger->debug('Circuit breaker call succeeded', [
            'service' => $event->getServiceKey(),
            'state' => $event->getCurrentState(),
            'execution_time' => $event->getExecutionTime(),
            'timestamp' => $event->getTimestamp(),
        ]);
    }

    /**
     * Log failed call events.
     */
    public function onCallFailed(CallFailedEvent $event): void
    {
        $this->logger->error('Circuit breaker call failed', [
            'service' => $event->getServiceKey(),
            'state' => $event->getCurrentState(),
            'exception' => $event->getExceptionClass(),
            'message' => $event->getExceptionMessage(),
            'failure_count' => $event->getFailureCount(),
            'failure_threshold' => $event->getFailureThreshold(),
            'will_trigger_open' => $event->willTriggerOpen(),
            'execution_time' => $event->getExecutionTime(),
            'timestamp' => $event->getTimestamp(),
        ]);
    }

    /**
     * Log fallback executed events.
     */
    public function onFallbackExecuted(FallbackExecutedEvent $event): void
    {
        $this->logger->warning('Circuit breaker fallback executed', [
            'service' => $event->getServiceKey(),
            'state' => $event->getCurrentState(),
            'original_exception' => $event->getOriginalException()::class,
            'original_message' => $event->getOriginalExceptionMessage(),
            'execution_time' => $event->getExecutionTime(),
            'timestamp' => $event->getTimestamp(),
        ]);
    }
}

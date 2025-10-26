<?php

declare(strict_types=1);

namespace Farzai\Breaker\Events;

use Psr\Log\LoggerInterface;

/**
 * Circuit breaker specific event dispatcher interface.
 *
 * Extends PSR-14 with additional functionality for error handling.
 */
interface EventDispatcherInterface extends \Psr\EventDispatcher\EventDispatcherInterface
{
    /**
     * Set the error handling strategy.
     *
     * @param  string  $strategy  One of: 'silent', 'collect', 'stop'
     */
    public function setErrorHandlingStrategy(string $strategy): void;

    /**
     * Get the error handling strategy.
     */
    public function getErrorHandlingStrategy(): string;

    /**
     * Set a logger for error reporting.
     */
    public function setLogger(LoggerInterface $logger): void;

    /**
     * Get errors that occurred during event dispatching (when using 'collect' strategy).
     *
     * @return array<int, array{event: object, listener: callable, exception: \Throwable}>
     */
    public function getDispatchErrors(): array;

    /**
     * Clear collected dispatch errors.
     */
    public function clearDispatchErrors(): void;
}

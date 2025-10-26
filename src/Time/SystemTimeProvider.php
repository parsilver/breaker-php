<?php

declare(strict_types=1);

namespace Farzai\Breaker\Time;

use Farzai\Breaker\Contracts\TimeProviderInterface;

/**
 * System time provider using PHP's built-in time functions.
 *
 * This is the default implementation used in production environments.
 */
final class SystemTimeProvider implements TimeProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function getCurrentTime(): int
    {
        return time();
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentTimeMs(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * {@inheritDoc}
     */
    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }

    /**
     * {@inheritDoc}
     */
    public function usleep(int $microseconds): void
    {
        usleep($microseconds);
    }
}

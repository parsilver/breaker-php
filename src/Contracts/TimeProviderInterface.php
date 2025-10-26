<?php

declare(strict_types=1);

namespace Farzai\Breaker\Contracts;

/**
 * Interface for time providers.
 *
 * This abstraction allows for testable time-dependent code by enabling
 * time to be mocked or frozen during tests.
 */
interface TimeProviderInterface
{
    /**
     * Get the current Unix timestamp.
     *
     * @return int Current time in seconds since Unix epoch
     */
    public function getCurrentTime(): int;

    /**
     * Get the current time in milliseconds.
     *
     * @return int Current time in milliseconds since Unix epoch
     */
    public function getCurrentTimeMs(): int;

    /**
     * Sleep for a given number of seconds.
     *
     * @param  int  $seconds  Number of seconds to sleep
     */
    public function sleep(int $seconds): void;

    /**
     * Sleep for a given number of microseconds.
     *
     * @param  int  $microseconds  Number of microseconds to sleep
     */
    public function usleep(int $microseconds): void;
}

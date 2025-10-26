<?php

declare(strict_types=1);

namespace Farzai\Breaker\Time;

use Farzai\Breaker\Contracts\TimeProviderInterface;

/**
 * Fake time provider for testing.
 *
 * Allows time to be frozen, advanced, and controlled during tests.
 */
final class FakeTimeProvider implements TimeProviderInterface
{
    /**
     * Current fake time in seconds.
     */
    private int $currentTime;

    /**
     * Create a new fake time provider.
     *
     * @param  int|null  $startTime  Starting timestamp (default: current time)
     */
    public function __construct(?int $startTime = null)
    {
        $this->currentTime = $startTime ?? time();
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentTime(): int
    {
        return $this->currentTime;
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentTimeMs(): int
    {
        return $this->currentTime * 1000;
    }

    /**
     * {@inheritDoc}
     */
    public function sleep(int $seconds): void
    {
        $this->currentTime += $seconds;
    }

    /**
     * {@inheritDoc}
     */
    public function usleep(int $microseconds): void
    {
        // Fake sleep - just advance time
        $this->currentTime += (int) ($microseconds / 1000000);
    }

    /**
     * Freeze time at the current moment.
     */
    public function freeze(): self
    {
        // Already frozen by default - this is just for API clarity
        return $this;
    }

    /**
     * Set the current time to a specific timestamp.
     *
     * @param  int  $timestamp  Unix timestamp
     */
    public function setCurrentTime(int $timestamp): self
    {
        $this->currentTime = $timestamp;

        return $this;
    }

    /**
     * Advance time by a number of seconds.
     *
     * @param  int  $seconds  Number of seconds to advance
     */
    public function advanceBy(int $seconds): self
    {
        $this->currentTime += $seconds;

        return $this;
    }

    /**
     * Travel to a specific point in time.
     *
     * @param  int  $timestamp  Target timestamp
     */
    public function travelTo(int $timestamp): self
    {
        $this->currentTime = $timestamp;

        return $this;
    }

    /**
     * Travel back to the current real time.
     */
    public function travelBack(): self
    {
        $this->currentTime = time();

        return $this;
    }
}

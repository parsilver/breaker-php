<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage\Decorators;

/**
 * Retry decorator for storage adapters.
 *
 * This decorator automatically retries failed storage operations,
 * useful for handling transient failures in network-based storage.
 *
 * Features:
 * - Exponential backoff
 * - Configurable retry attempts
 * - Jitter to prevent thundering herd
 */
class RetryStorageDecorator extends StorageAdapterDecorator
{
    /**
     * Create a new retry storage decorator.
     *
     * @param  \Farzai\Breaker\Storage\StorageAdapter  $adapter  The adapter to decorate
     * @param  int  $maxAttempts  Maximum retry attempts (including initial)
     * @param  int  $initialDelayMs  Initial delay in milliseconds
     * @param  float  $multiplier  Backoff multiplier
     * @param  bool  $useJitter  Add random jitter to prevent thundering herd
     */
    public function __construct(
        \Farzai\Breaker\Storage\StorageAdapter $adapter,
        private readonly int $maxAttempts = 3,
        private readonly int $initialDelayMs = 100,
        private readonly float $multiplier = 2.0,
        private readonly bool $useJitter = true,
    ) {
        parent::__construct($adapter);

        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('Max attempts must be at least 1');
        }

        if ($initialDelayMs < 0) {
            throw new \InvalidArgumentException('Initial delay must be non-negative');
        }

        if ($multiplier < 1.0) {
            throw new \InvalidArgumentException('Multiplier must be at least 1.0');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $key): ?string
    {
        return $this->retry(fn () => parent::read($key));
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $key, string $value, ?int $ttl = null): void
    {
        $this->retry(fn () => parent::write($key, $value, $ttl));
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        return $this->retry(fn () => parent::exists($key));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        $this->retry(fn () => parent::delete($key));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->retry(fn () => parent::clear());
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'retry('.$this->adapter->getName().')';
    }

    /**
     * Execute operation with retry logic.
     *
     * @template T
     *
     * @param  callable(): T  $operation  Operation to retry
     * @return T Operation result
     *
     * @throws \Throwable If all retries fail
     */
    private function retry(callable $operation): mixed
    {
        $lastException = null;
        $delay = $this->initialDelayMs;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                // Don't sleep on the last attempt
                if ($attempt < $this->maxAttempts) {
                    $this->sleep($delay);
                    $delay = (int) ($delay * $this->multiplier);
                }
            }
        }

        // All retries failed, throw the last exception
        // PHPStan check: $lastException cannot be null here since at least one iteration occurred
        if ($lastException === null) {
            throw new \RuntimeException('Retry failed without exception');
        }

        throw $lastException;
    }

    /**
     * Sleep with optional jitter.
     *
     * @param  int  $delayMs  Delay in milliseconds
     */
    private function sleep(int $delayMs): void
    {
        if ($delayMs <= 0) {
            return;
        }

        $actualDelay = $delayMs;

        if ($this->useJitter) {
            // Add ±25% jitter
            $jitter = (int) ($delayMs * 0.25);
            $actualDelay = $delayMs + random_int(-$jitter, $jitter);
            $actualDelay = max(0, $actualDelay); // Ensure non-negative
        }

        usleep($actualDelay * 1000); // Convert to microseconds
    }
}

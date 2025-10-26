<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage\Adapters;

use Farzai\Breaker\Exceptions\StorageException;
use Farzai\Breaker\Storage\StorageAdapter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Fallback storage adapter with cascading failure handling.
 *
 * Design Pattern: Chain of Responsibility
 * Purpose: Provide high availability through multiple storage backends
 *
 * This adapter tries multiple storage backends in order until one succeeds.
 * Common configuration:
 * 1. Primary: Redis (fast, distributed)
 * 2. Secondary: File storage (slower, local)
 * 3. Tertiary: In-memory (no persistence, always works)
 *
 * Benefits:
 * - High availability
 * - Graceful degradation
 * - Fault tolerance
 */
class FallbackStorageAdapter implements StorageAdapter
{
    /**
     * @var array<StorageAdapter>
     */
    private readonly array $adapters;

    /**
     * Create a new fallback storage adapter.
     *
     * @param  array<StorageAdapter>  $adapters  Storage adapters in priority order
     * @param  LoggerInterface|null  $logger  Optional logger for tracking failures
     *
     * @throws \InvalidArgumentException If no adapters provided
     */
    public function __construct(
        array $adapters,
        private readonly ?LoggerInterface $logger = null,
    ) {
        if (empty($adapters)) {
            throw new \InvalidArgumentException('At least one storage adapter is required');
        }

        $this->adapters = array_values($adapters); // Re-index
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $key): ?string
    {
        $lastException = null;

        foreach ($this->adapters as $index => $adapter) {
            try {
                return $adapter->read($key);
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->logFailure('read', $adapter, $e, $index);
                // Continue to next adapter
            }
        }

        // All adapters failed
        throw new StorageException(
            'All storage adapters failed for read operation',
            0,
            $lastException
        );
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $key, string $value, ?int $ttl = null): void
    {
        $successCount = 0;
        $lastException = null;

        foreach ($this->adapters as $index => $adapter) {
            try {
                $adapter->write($key, $value, $ttl);
                $successCount++;
                // Don't break - write to all available adapters for consistency
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->logFailure('write', $adapter, $e, $index);
                // Continue to next adapter
            }
        }

        // If at least one write succeeded, consider it successful
        if ($successCount > 0) {
            return;
        }

        // All adapters failed
        throw new StorageException(
            'All storage adapters failed for write operation',
            0,
            $lastException
        );
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        $lastException = null;

        foreach ($this->adapters as $index => $adapter) {
            try {
                if ($adapter->exists($key)) {
                    return true;
                }
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->logFailure('exists', $adapter, $e, $index);
                // Continue to next adapter
            }
        }

        // Either key doesn't exist anywhere, or all adapters failed
        // Return false for consistency
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        $successCount = 0;
        $lastException = null;

        foreach ($this->adapters as $index => $adapter) {
            try {
                $adapter->delete($key);
                $successCount++;
                // Don't break - delete from all adapters for consistency
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->logFailure('delete', $adapter, $e, $index);
                // Continue to next adapter
            }
        }

        // If at least one delete succeeded, consider it successful
        if ($successCount > 0) {
            return;
        }

        // All adapters failed
        throw new StorageException(
            'All storage adapters failed for delete operation',
            0,
            $lastException
        );
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $successCount = 0;
        $lastException = null;

        foreach ($this->adapters as $index => $adapter) {
            try {
                $adapter->clear();
                $successCount++;
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->logFailure('clear', $adapter, $e, $index);
                // Continue to next adapter
            }
        }

        // If at least one clear succeeded, consider it successful
        if ($successCount > 0) {
            return;
        }

        // All adapters failed
        throw new StorageException(
            'All storage adapters failed for clear operation',
            0,
            $lastException
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        $names = array_map(fn (StorageAdapter $adapter) => $adapter->getName(), $this->adapters);

        return 'fallback('.implode(',', $names).')';
    }

    /**
     * Get the list of storage adapters.
     *
     * @return array<StorageAdapter>
     */
    public function getAdapters(): array
    {
        return $this->adapters;
    }

    /**
     * Log adapter failure.
     *
     * @param  string  $operation  Operation that failed
     * @param  StorageAdapter  $adapter  Adapter that failed
     * @param  \Throwable  $exception  Exception that occurred
     * @param  int  $index  Adapter index in chain
     */
    private function logFailure(
        string $operation,
        StorageAdapter $adapter,
        \Throwable $exception,
        int $index
    ): void {
        $logger = $this->logger ?? new NullLogger;

        $logger->warning(
            "Fallback storage adapter #{$index} ({$adapter->getName()}) failed for {$operation}",
            [
                'adapter' => $adapter->getName(),
                'operation' => $operation,
                'exception' => $exception->getMessage(),
                'index' => $index,
            ]
        );
    }
}

<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage\Decorators;

/**
 * Metrics collection decorator for storage adapters.
 *
 * This decorator collects performance metrics and operation statistics,
 * making it easy to monitor storage health and performance.
 */
class MetricsStorageDecorator extends StorageAdapterDecorator
{
    /**
     * Create a new metrics storage decorator.
     *
     * @param  \Farzai\Breaker\Storage\StorageAdapter  $adapter  The adapter to decorate
     * @param  MetricsCollector  $metrics  Metrics collector
     */
    public function __construct(
        \Farzai\Breaker\Storage\StorageAdapter $adapter,
        private readonly MetricsCollector $metrics,
    ) {
        parent::__construct($adapter);
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $key): ?string
    {
        $startTime = microtime(true);
        $success = false;

        try {
            $result = parent::read($key);
            $success = true;

            return $result;
        } finally {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->metrics->recordOperation(
                operation: 'read',
                adapter: $this->adapter->getName(),
                durationMs: $duration,
                success: $success,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $key, string $value, ?int $ttl = null): void
    {
        $startTime = microtime(true);
        $success = false;

        try {
            parent::write($key, $value, $ttl);
            $success = true;
        } finally {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->metrics->recordOperation(
                operation: 'write',
                adapter: $this->adapter->getName(),
                durationMs: $duration,
                success: $success,
                tags: [
                    'value_size' => strlen($value),
                    'has_ttl' => $ttl !== null,
                ],
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        $startTime = microtime(true);
        $success = false;

        try {
            $result = parent::exists($key);
            $success = true;

            return $result;
        } finally {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->metrics->recordOperation(
                operation: 'exists',
                adapter: $this->adapter->getName(),
                durationMs: $duration,
                success: $success,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        $startTime = microtime(true);
        $success = false;

        try {
            parent::delete($key);
            $success = true;
        } finally {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->metrics->recordOperation(
                operation: 'delete',
                adapter: $this->adapter->getName(),
                durationMs: $duration,
                success: $success,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $startTime = microtime(true);
        $success = false;

        try {
            parent::clear();
            $success = true;
        } finally {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->metrics->recordOperation(
                operation: 'clear',
                adapter: $this->adapter->getName(),
                durationMs: $duration,
                success: $success,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'metrics('.$this->adapter->getName().')';
    }
}

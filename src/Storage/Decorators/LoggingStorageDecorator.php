<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage\Decorators;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Logging decorator for storage adapters.
 *
 * This decorator adds PSR-3 logging to all storage operations,
 * making it easy to debug and monitor storage behavior.
 */
class LoggingStorageDecorator extends StorageAdapterDecorator
{
    /**
     * Create a new logging storage decorator.
     *
     * @param  \Farzai\Breaker\Storage\StorageAdapter  $adapter  The adapter to decorate
     * @param  LoggerInterface  $logger  PSR-3 logger
     * @param  string  $successLevel  Log level for successful operations
     * @param  string  $errorLevel  Log level for failed operations
     */
    public function __construct(
        \Farzai\Breaker\Storage\StorageAdapter $adapter,
        private readonly LoggerInterface $logger,
        private readonly string $successLevel = LogLevel::DEBUG,
        private readonly string $errorLevel = LogLevel::ERROR,
    ) {
        parent::__construct($adapter);
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $key): ?string
    {
        $startTime = microtime(true);

        try {
            $result = parent::read($key);

            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->log($this->successLevel, 'Storage read succeeded', [
                'adapter' => $this->adapter->getName(),
                'key' => $key,
                'found' => $result !== null,
                'duration_ms' => round($duration, 2),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->log($this->errorLevel, 'Storage read failed', [
                'adapter' => $this->adapter->getName(),
                'key' => $key,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $key, string $value, ?int $ttl = null): void
    {
        $startTime = microtime(true);

        try {
            parent::write($key, $value, $ttl);

            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->log($this->successLevel, 'Storage write succeeded', [
                'adapter' => $this->adapter->getName(),
                'key' => $key,
                'value_length' => strlen($value),
                'ttl' => $ttl,
                'duration_ms' => round($duration, 2),
            ]);
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->log($this->errorLevel, 'Storage write failed', [
                'adapter' => $this->adapter->getName(),
                'key' => $key,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        $startTime = microtime(true);

        try {
            $result = parent::exists($key);

            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->log($this->successLevel, 'Storage exists check succeeded', [
                'adapter' => $this->adapter->getName(),
                'key' => $key,
                'exists' => $result,
                'duration_ms' => round($duration, 2),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->log($this->errorLevel, 'Storage exists check failed', [
                'adapter' => $this->adapter->getName(),
                'key' => $key,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        $startTime = microtime(true);

        try {
            parent::delete($key);

            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->log($this->successLevel, 'Storage delete succeeded', [
                'adapter' => $this->adapter->getName(),
                'key' => $key,
                'duration_ms' => round($duration, 2),
            ]);
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->log($this->errorLevel, 'Storage delete failed', [
                'adapter' => $this->adapter->getName(),
                'key' => $key,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $startTime = microtime(true);

        try {
            parent::clear();

            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->log($this->successLevel, 'Storage clear succeeded', [
                'adapter' => $this->adapter->getName(),
                'duration_ms' => round($duration, 2),
            ]);
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            $this->logger->log($this->errorLevel, 'Storage clear failed', [
                'adapter' => $this->adapter->getName(),
                'error' => $e->getMessage(),
                'duration_ms' => round($duration, 2),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'logging('.$this->adapter->getName().')';
    }
}

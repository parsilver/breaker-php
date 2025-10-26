<?php

declare(strict_types=1);

namespace Farzai\Breaker\Storage\Adapters;

use Farzai\Breaker\Storage\StorageAdapter;

/**
 * In-memory storage adapter implementation.
 *
 * This adapter stores circuit breaker state in memory.
 * Data is lost when the process ends, making it suitable for:
 * - Testing
 * - Short-lived processes
 * - Non-critical state storage
 *
 * Thread-safe for single-process usage only.
 */
class InMemoryStorageAdapter implements StorageAdapter
{
    /**
     * @var array<string, array{value: string, expiry: int|null}>
     */
    private array $storage = [];

    /**
     * {@inheritdoc}
     */
    public function read(string $key): ?string
    {
        if (! isset($this->storage[$key])) {
            return null;
        }

        $item = $this->storage[$key];

        // Check if item has expired
        if ($item['expiry'] !== null && time() > $item['expiry']) {
            unset($this->storage[$key]);

            return null;
        }

        return $item['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $key, string $value, ?int $ttl = null): void
    {
        $expiry = $ttl !== null ? time() + $ttl : null;

        $this->storage[$key] = [
            'value' => $value,
            'expiry' => $expiry,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $key): bool
    {
        if (! isset($this->storage[$key])) {
            return false;
        }

        $item = $this->storage[$key];

        // Check if item has expired
        if ($item['expiry'] !== null && time() > $item['expiry']) {
            unset($this->storage[$key]);

            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        unset($this->storage[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->storage = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'memory';
    }

    /**
     * Clean up expired items.
     *
     * This method can be called periodically to free memory.
     *
     * @return int Number of expired items removed
     */
    public function cleanupExpired(): int
    {
        $now = time();
        $removed = 0;

        foreach ($this->storage as $key => $item) {
            if ($item['expiry'] !== null && $now > $item['expiry']) {
                unset($this->storage[$key]);
                $removed++;
            }
        }

        return $removed;
    }
}

<?php

declare(strict_types=1);

namespace Farzai\Breaker\Exceptions;

use RuntimeException;

/**
 * Base exception for all storage-related errors.
 *
 * This exception is thrown when there are issues with storing or
 * retrieving circuit breaker state data.
 */
class StorageException extends RuntimeException
{
    /**
     * Create a storage exception for a specific service key.
     *
     * @param  string  $serviceKey  The service identifier
     * @param  string  $message  Error message
     * @param  int  $code  Error code
     * @param  \Throwable|null  $previous  Previous exception
     */
    public static function forServiceKey(
        string $serviceKey,
        string $message,
        int $code = 0,
        ?\Throwable $previous = null
    ): self {
        return new self(
            "Storage error for service '{$serviceKey}': {$message}",
            $code,
            $previous
        );
    }
}

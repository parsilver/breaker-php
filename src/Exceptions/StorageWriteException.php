<?php

declare(strict_types=1);

namespace Farzai\Breaker\Exceptions;

/**
 * Exception thrown when writing to storage fails.
 *
 * This can occur due to permission issues, disk space problems,
 * or network failures in distributed storage systems.
 */
class StorageWriteException extends StorageException
{
    /**
     * Create exception for permission denied errors.
     *
     * @param  string  $path  The file or resource path
     * @param  \Throwable|null  $previous  Previous exception
     */
    public static function permissionDenied(string $path, ?\Throwable $previous = null): self
    {
        return new self(
            "Permission denied writing to '{$path}'",
            0,
            $previous
        );
    }

    /**
     * Create exception for disk space errors.
     *
     * @param  string  $path  The file or resource path
     * @param  \Throwable|null  $previous  Previous exception
     */
    public static function diskFull(string $path, ?\Throwable $previous = null): self
    {
        return new self(
            "Disk full, cannot write to '{$path}'",
            0,
            $previous
        );
    }

    /**
     * Create exception for generic write failures.
     *
     * @param  string  $path  The file or resource path
     * @param  \Throwable|null  $previous  Previous exception
     */
    public static function writeFailed(string $path, ?\Throwable $previous = null): self
    {
        return new self(
            "Failed to write to '{$path}'",
            0,
            $previous
        );
    }
}

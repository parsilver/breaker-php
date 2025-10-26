<?php

declare(strict_types=1);

namespace Farzai\Breaker\Exceptions;

/**
 * Exception thrown when reading from storage fails.
 *
 * This can occur due to permission issues, corrupted data,
 * or network failures in distributed storage systems.
 */
class StorageReadException extends StorageException
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
            "Permission denied reading from '{$path}'",
            0,
            $previous
        );
    }

    /**
     * Create exception for file not found errors.
     *
     * @param  string  $path  The file or resource path
     * @param  \Throwable|null  $previous  Previous exception
     */
    public static function fileNotFound(string $path, ?\Throwable $previous = null): self
    {
        return new self(
            "File not found: '{$path}'",
            0,
            $previous
        );
    }

    /**
     * Create exception for corrupted data errors.
     *
     * @param  string  $path  The file or resource path
     * @param  \Throwable|null  $previous  Previous exception
     */
    public static function corruptedData(string $path, ?\Throwable $previous = null): self
    {
        return new self(
            "Corrupted or invalid data in '{$path}'",
            0,
            $previous
        );
    }

    /**
     * Create exception for generic read failures.
     *
     * @param  string  $path  The file or resource path
     * @param  \Throwable|null  $previous  Previous exception
     */
    public static function readFailed(string $path, ?\Throwable $previous = null): self
    {
        return new self(
            "Failed to read from '{$path}'",
            0,
            $previous
        );
    }
}

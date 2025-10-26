<?php

declare(strict_types=1);

use Farzai\Breaker\Exceptions\StorageException;
use Farzai\Breaker\Exceptions\StorageReadException;
use Farzai\Breaker\Exceptions\StorageWriteException;

describe('StorageExceptions', function () {
    describe('StorageException', function () {
        it('can create a storage exception', function () {
            $exception = new StorageException('Test message');

            expect($exception)->toBeInstanceOf(StorageException::class)
                ->and($exception->getMessage())->toBe('Test message');
        });

        it('can create with code', function () {
            $exception = new StorageException('Test message', 123);

            expect($exception->getCode())->toBe(123);
        });

        it('can create with previous exception', function () {
            $previous = new Exception('Previous exception');
            $exception = new StorageException('Test message', 0, $previous);

            expect($exception->getPrevious())->toBe($previous);
        });

        it('extends Exception', function () {
            $exception = new StorageException('Test');

            expect($exception)->toBeInstanceOf(Exception::class);
        });
    });

    describe('StorageReadException', function () {
        it('can create a storage read exception', function () {
            $exception = new StorageReadException('Read failed');

            expect($exception)->toBeInstanceOf(StorageReadException::class)
                ->and($exception->getMessage())->toBe('Read failed');
        });

        it('extends StorageException', function () {
            $exception = new StorageReadException('Test');

            expect($exception)->toBeInstanceOf(StorageException::class);
        });

        it('can create with code and previous exception', function () {
            $previous = new Exception('Original error');
            $exception = new StorageReadException('Read failed', 500, $previous);

            expect($exception->getCode())->toBe(500)
                ->and($exception->getPrevious())->toBe($previous);
        });
    });

    describe('StorageWriteException', function () {
        it('can create a storage write exception', function () {
            $exception = new StorageWriteException('Write failed');

            expect($exception)->toBeInstanceOf(StorageWriteException::class)
                ->and($exception->getMessage())->toBe('Write failed');
        });

        it('extends StorageException', function () {
            $exception = new StorageWriteException('Test');

            expect($exception)->toBeInstanceOf(StorageException::class);
        });

        it('can create with code and previous exception', function () {
            $previous = new Exception('Disk full');
            $exception = new StorageWriteException('Write failed', 503, $previous);

            expect($exception->getCode())->toBe(503)
                ->and($exception->getPrevious())->toBe($previous);
        });
    });

    it('can differentiate between read and write exceptions', function () {
        $readEx = new StorageReadException('Read failed');
        $writeEx = new StorageWriteException('Write failed');

        expect($readEx)->toBeInstanceOf(StorageReadException::class)
            ->and($writeEx)->toBeInstanceOf(StorageWriteException::class)
            ->and($readEx)->not->toBeInstanceOf(StorageWriteException::class)
            ->and($writeEx)->not->toBeInstanceOf(StorageReadException::class);
    });

    it('all storage exceptions can be caught as StorageException', function () {
        $exceptions = [
            new StorageException('Generic'),
            new StorageReadException('Read'),
            new StorageWriteException('Write'),
        ];

        foreach ($exceptions as $exception) {
            expect($exception)->toBeInstanceOf(StorageException::class);
        }
    });
});

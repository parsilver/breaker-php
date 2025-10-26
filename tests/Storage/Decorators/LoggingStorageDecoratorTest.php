<?php

declare(strict_types=1);

use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\Decorators\LoggingStorageDecorator;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

describe('LoggingStorageDecorator', function () {
    it('can create decorator with logger', function () {
        $adapter = new InMemoryStorageAdapter;
        $logger = new class implements LoggerInterface
        {
            public function emergency(\Stringable|string $message, array $context = []): void {}

            public function alert(\Stringable|string $message, array $context = []): void {}

            public function critical(\Stringable|string $message, array $context = []): void {}

            public function error(\Stringable|string $message, array $context = []): void {}

            public function warning(\Stringable|string $message, array $context = []): void {}

            public function notice(\Stringable|string $message, array $context = []): void {}

            public function info(\Stringable|string $message, array $context = []): void {}

            public function debug(\Stringable|string $message, array $context = []): void {}

            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $decorator = new LoggingStorageDecorator($adapter, $logger);

        expect($decorator)->toBeInstanceOf(LoggingStorageDecorator::class);
    });

    it('can create with custom log levels', function () {
        $adapter = new InMemoryStorageAdapter;
        $logger = new class implements LoggerInterface
        {
            public function emergency(\Stringable|string $message, array $context = []): void {}

            public function alert(\Stringable|string $message, array $context = []): void {}

            public function critical(\Stringable|string $message, array $context = []): void {}

            public function error(\Stringable|string $message, array $context = []): void {}

            public function warning(\Stringable|string $message, array $context = []): void {}

            public function notice(\Stringable|string $message, array $context = []): void {}

            public function info(\Stringable|string $message, array $context = []): void {}

            public function debug(\Stringable|string $message, array $context = []): void {}

            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $decorator = new LoggingStorageDecorator($adapter, $logger, LogLevel::INFO, LogLevel::CRITICAL);

        expect($decorator)->toBeInstanceOf(LoggingStorageDecorator::class);
    });

    it('returns correct name', function () {
        $adapter = new InMemoryStorageAdapter;
        $logger = new class implements LoggerInterface
        {
            public function emergency(\Stringable|string $message, array $context = []): void {}

            public function alert(\Stringable|string $message, array $context = []): void {}

            public function critical(\Stringable|string $message, array $context = []): void {}

            public function error(\Stringable|string $message, array $context = []): void {}

            public function warning(\Stringable|string $message, array $context = []): void {}

            public function notice(\Stringable|string $message, array $context = []): void {}

            public function info(\Stringable|string $message, array $context = []): void {}

            public function debug(\Stringable|string $message, array $context = []): void {}

            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $decorator = new LoggingStorageDecorator($adapter, $logger);

        expect($decorator->getName())->toBe('logging(memory)');
    });

    it('logs successful read operations', function () {
        $loggedLevel = null;
        $loggedMessage = null;

        $adapter = new InMemoryStorageAdapter;
        $adapter->write('key', 'value');

        $logger = new class($loggedLevel, $loggedMessage) implements LoggerInterface
        {
            public function __construct(private &$level, private &$message) {}

            public function emergency(\Stringable|string $message, array $context = []): void {}

            public function alert(\Stringable|string $message, array $context = []): void {}

            public function critical(\Stringable|string $message, array $context = []): void {}

            public function error(\Stringable|string $message, array $context = []): void {}

            public function warning(\Stringable|string $message, array $context = []): void {}

            public function notice(\Stringable|string $message, array $context = []): void {}

            public function info(\Stringable|string $message, array $context = []): void {}

            public function debug(\Stringable|string $message, array $context = []): void
            {
                $this->level = 'debug';
                $this->message = $message;
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->level = $level;
                $this->message = $message;
            }
        };

        $decorator = new LoggingStorageDecorator($adapter, $logger);
        $result = $decorator->read('key');

        expect($result)->toBe('value')
            ->and($loggedLevel)->toBe('debug');
    });

    it('logs successful write operations', function () {
        $loggedMessage = null;

        $adapter = new InMemoryStorageAdapter;

        $logger = new class($loggedMessage) implements LoggerInterface
        {
            public function __construct(private &$message) {}

            public function emergency(\Stringable|string $message, array $context = []): void {}

            public function alert(\Stringable|string $message, array $context = []): void {}

            public function critical(\Stringable|string $message, array $context = []): void {}

            public function error(\Stringable|string $message, array $context = []): void {}

            public function warning(\Stringable|string $message, array $context = []): void {}

            public function notice(\Stringable|string $message, array $context = []): void {}

            public function info(\Stringable|string $message, array $context = []): void {}

            public function debug(\Stringable|string $message, array $context = []): void
            {
                $this->message = $message;
            }

            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $decorator = new LoggingStorageDecorator($adapter, $logger);
        $decorator->write('key', 'value');

        expect($adapter->read('key'))->toBe('value');
    });

    it('logs errors on failed operations', function () {
        $loggedLevel = null;
        $loggedMessage = null;

        $adapter = new class implements \Farzai\Breaker\Storage\StorageAdapter
        {
            public function read(string $key): ?string
            {
                throw new Exception('Read failed');
            }

            public function write(string $key, string $value, ?int $ttl = null): void {}

            public function exists(string $key): bool
            {
                return false;
            }

            public function delete(string $key): void {}

            public function clear(): void {}

            public function getName(): string
            {
                return 'test';
            }
        };

        $logger = new class($loggedLevel, $loggedMessage) implements LoggerInterface
        {
            public function __construct(private &$level, private &$message) {}

            public function emergency(\Stringable|string $message, array $context = []): void {}

            public function alert(\Stringable|string $message, array $context = []): void {}

            public function critical(\Stringable|string $message, array $context = []): void {}

            public function error(\Stringable|string $message, array $context = []): void
            {
                $this->level = 'error';
                $this->message = $message;
            }

            public function warning(\Stringable|string $message, array $context = []): void {}

            public function notice(\Stringable|string $message, array $context = []): void {}

            public function info(\Stringable|string $message, array $context = []): void {}

            public function debug(\Stringable|string $message, array $context = []): void {}

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->level = $level;
                $this->message = $message;
            }
        };

        $decorator = new LoggingStorageDecorator($adapter, $logger);

        try {
            $decorator->read('key');
        } catch (Exception $e) {
            // Expected
        }

        expect($loggedLevel)->toBe('error');
    });

    it('logs exists operations', function () {
        $adapter = new InMemoryStorageAdapter;
        $adapter->write('key', 'value');

        $logger = new class implements LoggerInterface
        {
            public function emergency(\Stringable|string $message, array $context = []): void {}

            public function alert(\Stringable|string $message, array $context = []): void {}

            public function critical(\Stringable|string $message, array $context = []): void {}

            public function error(\Stringable|string $message, array $context = []): void {}

            public function warning(\Stringable|string $message, array $context = []): void {}

            public function notice(\Stringable|string $message, array $context = []): void {}

            public function info(\Stringable|string $message, array $context = []): void {}

            public function debug(\Stringable|string $message, array $context = []): void {}

            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $decorator = new LoggingStorageDecorator($adapter, $logger);
        $result = $decorator->exists('key');

        expect($result)->toBeTrue();
    });

    it('logs delete operations', function () {
        $adapter = new InMemoryStorageAdapter;
        $adapter->write('key', 'value');

        $logger = new class implements LoggerInterface
        {
            public function emergency(\Stringable|string $message, array $context = []): void {}

            public function alert(\Stringable|string $message, array $context = []): void {}

            public function critical(\Stringable|string $message, array $context = []): void {}

            public function error(\Stringable|string $message, array $context = []): void {}

            public function warning(\Stringable|string $message, array $context = []): void {}

            public function notice(\Stringable|string $message, array $context = []): void {}

            public function info(\Stringable|string $message, array $context = []): void {}

            public function debug(\Stringable|string $message, array $context = []): void {}

            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $decorator = new LoggingStorageDecorator($adapter, $logger);
        $decorator->delete('key');

        expect($adapter->exists('key'))->toBeFalse();
    });

    it('logs clear operations', function () {
        $adapter = new InMemoryStorageAdapter;
        $adapter->write('key', 'value');

        $logger = new class implements LoggerInterface
        {
            public function emergency(\Stringable|string $message, array $context = []): void {}

            public function alert(\Stringable|string $message, array $context = []): void {}

            public function critical(\Stringable|string $message, array $context = []): void {}

            public function error(\Stringable|string $message, array $context = []): void {}

            public function warning(\Stringable|string $message, array $context = []): void {}

            public function notice(\Stringable|string $message, array $context = []): void {}

            public function info(\Stringable|string $message, array $context = []): void {}

            public function debug(\Stringable|string $message, array $context = []): void {}

            public function log($level, \Stringable|string $message, array $context = []): void {}
        };

        $decorator = new LoggingStorageDecorator($adapter, $logger);
        $decorator->clear();

        expect($adapter->exists('key'))->toBeFalse();
    });
});

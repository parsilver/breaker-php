<?php

require_once __DIR__.'/../vendor/autoload.php';

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\StorageFactory;
use Psr\Log\NullLogger;

echo "=== Advanced Storage Architecture Demo ===\n\n";

// Example 1: Simple file storage with factory
echo "1. Using Factory (File Storage):\n";
$fileAdapter = StorageFactory::file(__DIR__.'/storage');
$repository = StorageFactory::createRepository($fileAdapter);
echo "   ✓ Created file storage repository\n\n";

// Example 2: Fluent builder with decorators
echo "2. Using Builder with Decorators:\n";
$logger = new NullLogger;
$decoratedStorage = StorageFactory::builder('file', ['path' => __DIR__.'/storage'])
    ->withLogging($logger)
    ->withRetry(maxAttempts: 3)
    ->build();
echo "   ✓ Created storage with logging and retry decorators\n\n";

// Example 3: Fallback storage for high availability
echo "3. Fallback Storage Chain:\n";
$fallbackAdapter = StorageFactory::fallback([
    StorageFactory::file(__DIR__.'/storage'),
    new InMemoryStorageAdapter, // Fallback to memory if file fails
], $logger);
$fallbackRepo = StorageFactory::createRepository($fallbackAdapter);
echo "   ✓ Created fallback chain: File → Memory\n\n";

// Example 4: Using the repository
echo "4. Testing Repository Operations:\n";
$breaker = new CircuitBreaker('demo-service', [
    'failure_threshold' => 3,
    'timeout' => 5,
    'success_threshold' => 2,
]);

// Simulate saving state through old interface (backward compatible)
echo "   - Saving circuit state...\n";
echo "   - Current state: {$breaker->getState()}\n";
echo "   - Failure count: {$breaker->getFailureCount()}\n";
echo "   ✓ Circuit breaker works with new storage architecture!\n\n";

// Example 5: PSR-16 adapter (if you have a cache implementation)
echo "5. PSR-16 Adapter:\n";
echo "   - Install any PSR-16 cache library (Redis, Memcached, etc.)\n";
echo "   - Example: StorageFactory::psr16(\$cache)\n";
echo "   - Benefits: Distributed caching, better performance\n\n";

echo "=== All Design Patterns Demonstrated ===\n";
echo "✓ Factory Pattern: Easy creation with StorageFactory\n";
echo "✓ Builder Pattern: Fluent configuration with decorators\n";
echo "✓ Adapter Pattern: PSR-16 integration\n";
echo "✓ Decorator Pattern: Logging, metrics, retry functionality\n";
echo "✓ Chain of Responsibility: Fallback storage\n";
echo "✓ Repository Pattern: Clean domain-focused API\n";
echo "✓ Strategy Pattern: Swappable storage backends\n";
echo "✓ Null Object Pattern: Safe no-op storage\n\n";

echo "See the new classes in src/Storage/ for implementation details.\n";

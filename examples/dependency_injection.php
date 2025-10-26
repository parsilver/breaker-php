<?php

require_once __DIR__.'/../vendor/autoload.php';

use Farzai\Breaker\Breaker;
use Farzai\Breaker\BreakerManager;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\DefaultCircuitStateRepository;
use Farzai\Breaker\Storage\JsonStorageSerializer;
use Psr\Log\NullLogger;

/**
 * Dependency Injection Best Practices
 *
 * This example demonstrates proper dependency injection patterns
 * for production code and testing.
 */
echo "=== Dependency Injection Best Practices ===\n\n";

// ============================================================================
// Example 1: Injectable Service Class (Recommended for Production)
// ============================================================================

echo "Example 1: Injectable Service Class\n";
echo "------------------------------------\n";

/**
 * Example service class that uses dependency injection.
 * This is the recommended approach for production code.
 */
class UserApiClient
{
    private BreakerManager $breaker;

    private string $serviceKey = 'user-api';

    public function __construct(BreakerManager $breaker)
    {
        $this->breaker = $breaker;

        // Configure the circuit breaker for this service
        $this->breaker->configure($this->serviceKey, [
            'failure_threshold' => 3,
            'timeout' => 30,
            'success_threshold' => 2,
        ]);
    }

    public function getUser(int $id): array
    {
        return $this->breaker->protect(
            $this->serviceKey,
            function () use ($id) {
                // Simulate API call
                echo "Fetching user #{$id} from API...\n";

                return ['id' => $id, 'name' => "User {$id}"];
            },
            fallback: function ($exception) use ($id) {
                echo "API failed, using cache for user #{$id}\n";

                return ['id' => $id, 'name' => "Cached User {$id}", 'cached' => true];
            }
        );
    }

    public function createUser(array $data): array
    {
        return $this->breaker->protect(
            $this->serviceKey,
            function () use ($data) {
                echo "Creating user: {$data['name']}\n";

                return array_merge(['id' => rand(1000, 9999)], $data);
            }
        );
    }
}

// Create the dependency
$manager = new BreakerManager;

// Inject into the service
$userApi = new UserApiClient($manager);

// Use the service
$user = $userApi->getUser(1);
echo 'Fetched: '.json_encode($user)."\n\n";

// ============================================================================
// Example 2: Testing with Dependency Injection
// ============================================================================

echo "Example 2: Testing with Mock Manager\n";
echo "-------------------------------------\n";

/**
 * Example of how to test a service that uses BreakerManager.
 * You can inject a pre-configured manager for testing.
 */

// Create a test manager with specific configuration
$testManager = new BreakerManager;
$testManager->configure('user-api', [
    'failure_threshold' => 1, // Fail fast in tests
]);

// Inject test manager
$testUserApi = new UserApiClient($testManager);

// Now your tests can verify behavior with controlled circuit breaker
echo "Testing with controlled circuit breaker...\n";
$result = $testUserApi->getUser(123);
echo 'Test result: '.json_encode($result)."\n\n";

// ============================================================================
// Example 3: Using Facade for Quick Scripts (Acceptable for Scripts)
// ============================================================================

echo "Example 3: Facade for Quick Scripts\n";
echo "------------------------------------\n";

/**
 * For one-off scripts or CLI commands, the facade is acceptable.
 * But for production services, prefer dependency injection.
 */

// Quick script that doesn't need DI
$data = Breaker::protect('quick-api', function () {
    echo "Quick API call for script...\n";

    return ['script' => 'result'];
});

echo 'Script result: '.json_encode($data)."\n\n";

// ============================================================================
// Example 4: Factory Pattern
// ============================================================================

echo "Example 4: Factory Pattern\n";
echo "--------------------------\n";

/**
 * Use a factory to create configured managers.
 */
class BreakerFactory
{
    public static function createForProduction(): BreakerManager
    {
        $repository = new DefaultCircuitStateRepository(
            new InMemoryStorageAdapter,
            new JsonStorageSerializer
        );

        $logger = new NullLogger; // Use your real logger

        return new BreakerManager($repository, $logger);
    }

    public static function createForTesting(): BreakerManager
    {
        // Test manager with in-memory storage
        return new BreakerManager;
    }
}

// Use in production
$prodManager = BreakerFactory::createForProduction();
$prodApi = new UserApiClient($prodManager);

echo "Production API client created with persistent storage\n\n";

// ============================================================================
// Example 5: Shared Manager Across Multiple Services
// ============================================================================

echo "Example 5: Shared Manager Pattern\n";
echo "----------------------------------\n";

/**
 * Multiple service clients can share the same BreakerManager.
 * This allows centralized configuration and health monitoring.
 */
class PaymentApiClient
{
    private BreakerManager $breaker;

    public function __construct(BreakerManager $breaker)
    {
        $this->breaker = $breaker;

        $this->breaker->configure('payment-api', [
            'failure_threshold' => 2, // Critical service
            'timeout' => 60,
        ]);
    }

    public function processPayment(float $amount): array
    {
        return $this->breaker->protect(
            'payment-api',
            fn () => ['status' => 'success', 'amount' => $amount]
        );
    }
}

class NotificationApiClient
{
    private BreakerManager $breaker;

    public function __construct(BreakerManager $breaker)
    {
        $this->breaker = $breaker;

        $this->breaker->configure('notification-api', [
            'failure_threshold' => 5, // Less critical
            'timeout' => 30,
        ]);
    }

    public function sendEmail(string $to, string $subject): bool
    {
        return $this->breaker->protect(
            'notification-api',
            fn () => true
        );
    }
}

// Single manager shared across all services
$sharedManager = new BreakerManager;

$paymentApi = new PaymentApiClient($sharedManager);
$notificationApi = new NotificationApiClient($sharedManager);
$userApi2 = new UserApiClient($sharedManager);

// Use the services
$paymentApi->processPayment(99.99);
$notificationApi->sendEmail('user@example.com', 'Test');
$userApi2->getUser(456);

// Centralized health monitoring
echo "Health status for all services:\n";
$healthReports = $sharedManager->healthReport();
foreach ($healthReports as $service => $health) {
    echo "  {$service}: {$health->status->value}\n";
}

echo "\n";

// ============================================================================
// Example 6: Facade with Injectable Manager for Testing
// ============================================================================

echo "Example 6: Testing Facade-Using Code\n";
echo "-------------------------------------\n";

/**
 * Even if your code uses the Breaker facade, you can still test it
 * by injecting a custom manager into the facade.
 */

// Code that uses the facade
function doSomethingWithFacade(): array
{
    return Breaker::protect('facade-service', fn () => ['facade' => 'works']);
}

// In tests, inject a test manager
$testManager2 = new BreakerManager;
$testManager2->configure('facade-service', ['failure_threshold' => 1]);

Breaker::setManager($testManager2);

// Now the facade uses your test manager
$result = doSomethingWithFacade();
echo 'Facade test result: '.json_encode($result)."\n";
echo 'Manager in facade is test manager: '.(Breaker::getManager() === $testManager2 ? 'Yes' : 'No')."\n\n";

// Clean up
Breaker::clearManager();

// ============================================================================
// Summary
// ============================================================================

echo "=== Best Practices Summary ===\n\n";

echo "✅ DO:\n";
echo "  - Inject BreakerManager into service classes\n";
echo "  - Use constructor injection for dependencies\n";
echo "  - Share a single manager across multiple services\n";
echo "  - Use factory pattern for creating configured managers\n";
echo "  - Inject test managers for unit testing\n\n";

echo "⚠️  ACCEPTABLE:\n";
echo "  - Use Breaker facade in quick scripts and CLI commands\n";
echo "  - Use facade in tests by injecting test manager\n\n";

echo "❌ AVOID:\n";
echo "  - Using facade directly in production service classes\n";
echo "  - Creating new managers everywhere (share one instance)\n";
echo "  - Accessing static methods from testable classes\n\n";

echo "Example Structure:\n";
echo "┌─────────────────────────────────────┐\n";
echo "│         BreakerManager              │\n";
echo "│  (Shared via Dependency Injection)  │\n";
echo "└──────────────┬──────────────────────┘\n";
echo "               │\n";
echo "       ├───────┼────────┐\n";
echo "       ▼       ▼        ▼\n";
echo "   UserApi  PaymentApi  NotificationApi\n";
echo "   (injected) (injected)  (injected)\n";

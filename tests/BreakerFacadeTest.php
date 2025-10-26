<?php

use Farzai\Breaker\Breaker;
use Farzai\Breaker\BreakerManager;
use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Health\HealthReport;
use Farzai\Breaker\Health\HealthStatus;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\DefaultCircuitStateRepository;
use Farzai\Breaker\Storage\JsonStorageSerializer;
use Farzai\Breaker\Time\FakeTimeProvider;
use Psr\Log\NullLogger;

describe('Breaker Facade', function () {
    beforeEach(function () {
        // Clear manager before each test to ensure isolation
        Breaker::clearManager();
    });

    afterEach(function () {
        // Clean up after each test
        Breaker::flush();
        Breaker::clearManager();
    });

    it('creates default manager on first use', function () {
        $manager = Breaker::getManager();

        expect($manager)->toBeInstanceOf(BreakerManager::class);
    });

    it('can protect a callable and return result', function () {
        $result = Breaker::protect('test-service', function () {
            return 'success';
        });

        expect($result)->toBe('success');
    });

    it('executes fallback when callable fails', function () {
        $result = Breaker::protect(
            'test-service',
            function () {
                throw new Exception('Service failed');
            },
            fallback: function ($exception) {
                return 'fallback-result';
            }
        );

        expect($result)->toBe('fallback-result');
    });

    it('can get circuit breaker instance', function () {
        $breaker = Breaker::instance('test-service');

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
        expect($breaker->getServiceKey())->toBe('test-service');
    });

    it('can configure a service', function () {
        Breaker::configure('test-service', ['failure_threshold' => 10]);

        $breaker = Breaker::instance('test-service');

        expect($breaker->getFailureThreshold())->toBe(10);
    });

    it('can forget a service', function () {
        $breaker1 = Breaker::instance('test-service');
        $result = Breaker::forget('test-service');

        expect($result)->toBeTrue();

        $breaker2 = Breaker::instance('test-service');

        expect($breaker1)->not->toBe($breaker2);
    });

    it('can flush all services', function () {
        Breaker::instance('service-1');
        Breaker::instance('service-2');

        Breaker::flush();

        $all = Breaker::all();

        expect($all)->toBeEmpty();
    });

    it('can get all managed instances', function () {
        Breaker::instance('service-1');
        Breaker::instance('service-2');
        Breaker::instance('service-3');

        $all = Breaker::all();

        expect($all)->toHaveCount(3);
        expect($all)->toHaveKeys(['service-1', 'service-2', 'service-3']);
    });

    it('can get health report for a specific service', function () {
        Breaker::instance('test-service');

        $health = Breaker::healthReport('test-service');

        expect($health)->toBeInstanceOf(HealthReport::class);
        expect($health->status)->toBe(HealthStatus::HEALTHY);
    });

    it('can get health reports for all services', function () {
        Breaker::instance('service-1');
        Breaker::instance('service-2');

        $reports = Breaker::healthReport();

        expect($reports)->toBeArray();
        expect($reports)->toHaveCount(2);
    });

    it('can set default repository', function () {
        $repository = new DefaultCircuitStateRepository(
            new InMemoryStorageAdapter,
            new JsonStorageSerializer
        );

        $result = Breaker::setDefaultRepository($repository);

        expect($result)->toBeInstanceOf(BreakerManager::class);

        $breaker = Breaker::instance('test-service');
        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set default logger', function () {
        $logger = new NullLogger;

        $result = Breaker::setDefaultLogger($logger);

        expect($result)->toBeInstanceOf(BreakerManager::class);

        $breaker = Breaker::instance('test-service');
        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set default time provider', function () {
        $timeProvider = new FakeTimeProvider;

        $result = Breaker::setDefaultTimeProvider($timeProvider);

        expect($result)->toBeInstanceOf(BreakerManager::class);

        $breaker = Breaker::instance('test-service');
        expect($breaker->getTimeProvider())->toBe($timeProvider);
    });

    it('can inject custom manager', function () {
        $customManager = new BreakerManager;
        $customManager->configure('test-service', ['failure_threshold' => 20]);

        Breaker::setManager($customManager);

        $breaker = Breaker::instance('test-service');

        expect($breaker->getFailureThreshold())->toBe(20);
    });

    it('getManager returns same instance on multiple calls', function () {
        $manager1 = Breaker::getManager();
        $manager2 = Breaker::getManager();

        expect($manager1)->toBe($manager2);
    });

    it('clearManager resets the manager', function () {
        $manager1 = Breaker::getManager();

        Breaker::clearManager();

        $manager2 = Breaker::getManager();

        expect($manager1)->not->toBe($manager2);
    });

    it('supports method chaining through configure', function () {
        $result = Breaker::configure('service-1', ['failure_threshold' => 5])
            ->configure('service-2', ['failure_threshold' => 10]);

        expect($result)->toBeInstanceOf(BreakerManager::class);

        $breaker1 = Breaker::instance('service-1');
        $breaker2 = Breaker::instance('service-2');

        expect($breaker1->getFailureThreshold())->toBe(5);
        expect($breaker2->getFailureThreshold())->toBe(10);
    });

    it('isolates tests when using clearManager', function () {
        // First test scenario
        Breaker::instance('test-service');
        expect(Breaker::all())->toHaveCount(1);

        // Clear and start fresh
        Breaker::flush();
        Breaker::clearManager();

        // Second test scenario should have clean state
        expect(Breaker::all())->toBeEmpty();
    });

    it('allows testing with mock manager', function () {
        $mockManager = new BreakerManager;

        // Pre-configure the mock manager
        $mockManager->configure('api-service', ['failure_threshold' => 1]);

        // Inject mock into facade
        Breaker::setManager($mockManager);

        // Now code using the facade will use the mock
        $result = Breaker::protect('api-service', function () {
            return 'mocked';
        });

        expect($result)->toBe('mocked');

        $breaker = Breaker::instance('api-service');
        expect($breaker->getFailureThreshold())->toBe(1);
    });

    it('protect with config passes configuration correctly', function () {
        Breaker::protect(
            'test-service',
            function () {
                return 'success';
            },
            config: ['failure_threshold' => 15]
        );

        $breaker = Breaker::instance('test-service');

        expect($breaker->getFailureThreshold())->toBe(15);
    });

    it('multiple services maintain separate states', function () {
        // Create multiple services
        $result1 = Breaker::protect('service-1', fn () => 'result-1');
        $result2 = Breaker::protect('service-2', fn () => 'result-2');
        $result3 = Breaker::protect('service-3', fn () => 'result-3');

        expect($result1)->toBe('result-1');
        expect($result2)->toBe('result-2');
        expect($result3)->toBe('result-3');

        // Verify they are separate instances
        $breaker1 = Breaker::instance('service-1');
        $breaker2 = Breaker::instance('service-2');
        $breaker3 = Breaker::instance('service-3');

        expect($breaker1)->not->toBe($breaker2);
        expect($breaker2)->not->toBe($breaker3);
        expect($breaker1)->not->toBe($breaker3);
    });
});

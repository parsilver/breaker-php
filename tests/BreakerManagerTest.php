<?php

use Farzai\Breaker\BreakerManager;
use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;
use Farzai\Breaker\Health\HealthReport;
use Farzai\Breaker\Health\HealthStatus;
use Farzai\Breaker\Storage\Adapters\InMemoryStorageAdapter;
use Farzai\Breaker\Storage\DefaultCircuitStateRepository;
use Farzai\Breaker\Storage\JsonStorageSerializer;
use Farzai\Breaker\Time\FakeTimeProvider;
use Psr\Log\NullLogger;

describe('BreakerManager', function () {
    it('creates a new manager instance', function () {
        $manager = new BreakerManager;

        expect($manager)->toBeInstanceOf(BreakerManager::class);
    });

    it('can protect a callable and return result', function () {
        $manager = new BreakerManager;

        $result = $manager->protect('test-service', function () {
            return 'success';
        });

        expect($result)->toBe('success');
    });

    it('executes fallback when callable fails', function () {
        $manager = new BreakerManager;

        $result = $manager->protect(
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

    it('creates and caches circuit breaker instances', function () {
        $manager = new BreakerManager;

        $breaker1 = $manager->instance('test-service');
        $breaker2 = $manager->instance('test-service');

        expect($breaker1)->toBeInstanceOf(CircuitBreaker::class);
        expect($breaker1)->toBe($breaker2); // Same instance
    });

    it('creates separate instances for different services', function () {
        $manager = new BreakerManager;

        $breaker1 = $manager->instance('service-1');
        $breaker2 = $manager->instance('service-2');

        expect($breaker1)->not->toBe($breaker2);
        expect($breaker1->getServiceKey())->toBe('service-1');
        expect($breaker2->getServiceKey())->toBe('service-2');
    });

    it('applies configuration when creating instance', function () {
        $manager = new BreakerManager;

        $breaker = $manager->instance('test-service', [
            'failure_threshold' => 10,
            'timeout' => 60,
            'success_threshold' => 3,
        ]);

        expect($breaker->getFailureThreshold())->toBe(10);
        expect($breaker->getTimeout())->toBe(60);
        expect($breaker->getSuccessThreshold())->toBe(3);
    });

    it('can configure default settings for a service', function () {
        $manager = new BreakerManager;

        $manager->configure('test-service', [
            'failure_threshold' => 10,
        ]);

        $breaker = $manager->instance('test-service');

        expect($breaker->getFailureThreshold())->toBe(10);
    });

    it('merges runtime config with stored config', function () {
        $manager = new BreakerManager;

        $manager->configure('test-service', [
            'failure_threshold' => 10,
            'timeout' => 30,
        ]);

        $breaker = $manager->instance('test-service', [
            'timeout' => 60, // Override
        ]);

        expect($breaker->getFailureThreshold())->toBe(10); // From configure
        expect($breaker->getTimeout())->toBe(60); // From instance
    });

    it('can forget a service instance', function () {
        $manager = new BreakerManager;

        $breaker1 = $manager->instance('test-service');
        $result = $manager->forget('test-service');

        expect($result)->toBeTrue();

        $breaker2 = $manager->instance('test-service');

        expect($breaker1)->not->toBe($breaker2); // Different instance after forget
    });

    it('returns false when forgetting non-existent service', function () {
        $manager = new BreakerManager;

        $result = $manager->forget('non-existent');

        expect($result)->toBeFalse();
    });

    it('can flush all instances', function () {
        $manager = new BreakerManager;

        $breaker1 = $manager->instance('service-1');
        $breaker2 = $manager->instance('service-2');

        $manager->flush();

        $breaker3 = $manager->instance('service-1');

        expect($breaker3)->not->toBe($breaker1); // New instance after flush
        expect($manager->all())->toHaveCount(1); // Only service-1 after flush
    });

    it('can get all managed instances', function () {
        $manager = new BreakerManager;

        $manager->instance('service-1');
        $manager->instance('service-2');
        $manager->instance('service-3');

        $all = $manager->all();

        expect($all)->toHaveCount(3);
        expect($all)->toHaveKeys(['service-1', 'service-2', 'service-3']);
    });

    it('can get health report for a specific service', function () {
        $manager = new BreakerManager;

        $manager->instance('test-service');

        $health = $manager->healthReport('test-service');

        expect($health)->toBeInstanceOf(HealthReport::class);
        expect($health->status)->toBe(HealthStatus::HEALTHY);
    });

    it('can get health reports for all services', function () {
        $manager = new BreakerManager;

        $manager->instance('service-1');
        $manager->instance('service-2');

        $reports = $manager->healthReport();

        expect($reports)->toBeArray();
        expect($reports)->toHaveCount(2);
        expect($reports['service-1'])->toBeInstanceOf(HealthReport::class);
        expect($reports['service-2'])->toBeInstanceOf(HealthReport::class);
    });

    it('uses default repository for all instances', function () {
        $repository = new DefaultCircuitStateRepository(
            new InMemoryStorageAdapter,
            new JsonStorageSerializer
        );

        $manager = new BreakerManager($repository);

        $breaker1 = $manager->instance('service-1');
        $breaker2 = $manager->instance('service-2');

        // Both should share the same repository
        // We can verify this by making changes through one and seeing them in the other
        expect($breaker1)->toBeInstanceOf(CircuitBreaker::class);
        expect($breaker2)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('uses default logger for all instances', function () {
        $logger = new NullLogger;
        $manager = new BreakerManager(null, $logger);

        $breaker = $manager->instance('test-service');

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('uses default time provider for all instances', function () {
        $timeProvider = new FakeTimeProvider;
        $manager = new BreakerManager(null, null, $timeProvider);

        $breaker = $manager->instance('test-service');

        expect($breaker->getTimeProvider())->toBe($timeProvider);
    });

    it('can set default repository after construction', function () {
        $manager = new BreakerManager;

        $repository = new DefaultCircuitStateRepository(
            new InMemoryStorageAdapter,
            new JsonStorageSerializer
        );

        $manager->setDefaultRepository($repository);

        $breaker = $manager->instance('test-service');

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set default logger after construction', function () {
        $manager = new BreakerManager;

        $logger = new NullLogger;
        $manager->setDefaultLogger($logger);

        $breaker = $manager->instance('test-service');

        expect($breaker)->toBeInstanceOf(CircuitBreaker::class);
    });

    it('can set default time provider after construction', function () {
        $manager = new BreakerManager;

        $timeProvider = new FakeTimeProvider;
        $manager->setDefaultTimeProvider($timeProvider);

        $breaker = $manager->instance('test-service');

        expect($breaker->getTimeProvider())->toBe($timeProvider);
    });

    it('protect method respects circuit state', function () {
        $manager = new BreakerManager;

        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $manager->protect('test-service', function () {
                    throw new Exception('fail');
                });
            } catch (Exception $e) {
                // Expected
            }
        }

        // Circuit should now be open
        $breaker = $manager->instance('test-service');
        expect($breaker->getState())->toBe('open');

        // Next call should throw CircuitOpenException
        try {
            $manager->protect('test-service', function () {
                return 'should not execute';
            });
            throw new Exception('Should have thrown CircuitOpenException');
        } catch (CircuitOpenException $e) {
            expect($e)->toBeInstanceOf(CircuitOpenException::class);
        }
    });

    it('protect with fallback handles circuit open state', function () {
        $manager = new BreakerManager;

        // Trip the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $manager->protect('test-service', function () {
                    throw new Exception('fail');
                });
            } catch (Exception $e) {
                // Expected
            }
        }

        // Use fallback when circuit is open
        $result = $manager->protect(
            'test-service',
            function () {
                return 'should not execute';
            },
            fallback: function ($exception) {
                return 'fallback executed';
            }
        );

        expect($result)->toBe('fallback executed');
    });

    it('protect method passes config to instance', function () {
        $manager = new BreakerManager;

        $manager->protect(
            'test-service',
            function () {
                return 'success';
            },
            config: ['failure_threshold' => 20]
        );

        $breaker = $manager->instance('test-service');

        expect($breaker->getFailureThreshold())->toBe(20);
    });

    it('configure returns manager for chaining', function () {
        $manager = new BreakerManager;

        $result = $manager->configure('test-service', ['failure_threshold' => 10]);

        expect($result)->toBe($manager);
    });

    it('setter methods return manager for chaining', function () {
        $manager = new BreakerManager;
        $repository = new DefaultCircuitStateRepository(
            new InMemoryStorageAdapter,
            new JsonStorageSerializer
        );
        $logger = new NullLogger;
        $timeProvider = new FakeTimeProvider;

        $result1 = $manager->setDefaultRepository($repository);
        $result2 = $manager->setDefaultLogger($logger);
        $result3 = $manager->setDefaultTimeProvider($timeProvider);

        expect($result1)->toBe($manager);
        expect($result2)->toBe($manager);
        expect($result3)->toBe($manager);
    });
});

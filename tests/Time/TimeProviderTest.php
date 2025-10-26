<?php

declare(strict_types=1);

use Farzai\Breaker\Time\FakeTimeProvider;
use Farzai\Breaker\Time\SystemTimeProvider;

describe('SystemTimeProvider', function () {
    it('can create a system time provider', function () {
        $provider = new SystemTimeProvider;

        expect($provider)->toBeInstanceOf(SystemTimeProvider::class);
    });

    it('returns current timestamp', function () {
        $provider = new SystemTimeProvider;
        $before = time();
        $timestamp = $provider->getCurrentTime();
        $after = time();

        expect($timestamp)->toBeGreaterThanOrEqual($before)
            ->and($timestamp)->toBeLessThanOrEqual($after);
    });

    it('returns current time in milliseconds', function () {
        $provider = new SystemTimeProvider;
        $timestamp = $provider->getCurrentTimeMs();

        expect($timestamp)->toBeGreaterThan(time() * 1000 - 1000); // Allow 1 second tolerance
    });

    it('usleep pauses execution', function () {
        $provider = new SystemTimeProvider;
        $before = microtime(true);
        $provider->usleep(100000); // Sleep for 100ms (100000 microseconds)
        $after = microtime(true);

        $elapsed = ($after - $before) * 1000; // Convert to milliseconds

        expect($elapsed)->toBeGreaterThanOrEqual(95); // Allow some tolerance
    });

    it('sleep with seconds works correctly', function () {
        $provider = new SystemTimeProvider;
        $before = time();
        $provider->sleep(1); // Sleep for 1 second
        $after = time();

        expect($after - $before)->toBeGreaterThanOrEqual(1);
    });
});

describe('FakeTimeProvider', function () {
    it('can create a fake time provider with default time', function () {
        $provider = new FakeTimeProvider;

        expect($provider)->toBeInstanceOf(FakeTimeProvider::class);
    });

    it('can create with specific initial time', function () {
        $provider = new FakeTimeProvider(1234567890);

        expect($provider->getCurrentTime())->toBe(1234567890);
    });

    it('returns frozen time by default', function () {
        $provider = new FakeTimeProvider(1000);

        expect($provider->getCurrentTime())->toBe(1000);
        expect($provider->getCurrentTime())->toBe(1000);
        expect($provider->getCurrentTime())->toBe(1000);
    });

    it('can advance time forward', function () {
        $provider = new FakeTimeProvider(1000);

        expect($provider->getCurrentTime())->toBe(1000);

        $provider->advanceBy(500);

        expect($provider->getCurrentTime())->toBe(1500);
    });

    it('can set time to specific moment', function () {
        $provider = new FakeTimeProvider(1000);

        $provider->setCurrentTime(5000);

        expect($provider->getCurrentTime())->toBe(5000);
    });

    it('freeze returns provider for chaining', function () {
        $provider = new FakeTimeProvider(1000);

        $result = $provider->freeze();

        expect($result)->toBe($provider);
    });

    it('returns time in milliseconds correctly', function () {
        $provider = new FakeTimeProvider(1000);

        expect($provider->getCurrentTimeMs())->toBe(1000 * 1000);
    });

    it('sleep advances frozen time', function () {
        $provider = new FakeTimeProvider(1000);

        $provider->sleep(5);

        expect($provider->getCurrentTime())->toBe(1005);
    });

    it('usleep advances time correctly', function () {
        $provider = new FakeTimeProvider(1000);

        $provider->usleep(500000); // 500ms in microseconds

        expect($provider->getCurrentTime())->toBe(1000); // Should not advance as it's less than 1 second
    });

    it('can chain advance operations', function () {
        $provider = new FakeTimeProvider(1000);

        $provider->advanceBy(100)
            ->advanceBy(200)
            ->advanceBy(300);

        expect($provider->getCurrentTime())->toBe(1600);
    });

    it('setCurrentTime returns provider for chaining', function () {
        $provider = new FakeTimeProvider(1000);

        $result = $provider->setCurrentTime(2000);

        expect($result)->toBe($provider)
            ->and($provider->getCurrentTime())->toBe(2000);
    });

    it('can simulate passage of time with multiple advances', function () {
        $provider = new FakeTimeProvider(0);

        // Simulate 1 hour passing
        $provider->advanceBy(3600);
        expect($provider->getCurrentTime())->toBe(3600);

        // Simulate another 30 minutes
        $provider->advanceBy(1800);
        expect($provider->getCurrentTime())->toBe(5400);
    });

    it('can travel to specific timestamp', function () {
        $provider = new FakeTimeProvider(1000);

        $provider->travelTo(9999);

        expect($provider->getCurrentTime())->toBe(9999);
    });

    it('can travel back to current time', function () {
        $provider = new FakeTimeProvider(0);

        $before = time();
        $provider->travelBack();
        $after = $provider->getCurrentTime();

        expect($after)->toBeGreaterThanOrEqual($before);
    });

    it('getCurrentTimeMs works with frozen time', function () {
        $provider = new FakeTimeProvider(5);

        expect($provider->getCurrentTimeMs())->toBe(5000);
    });
});

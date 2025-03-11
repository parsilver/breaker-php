# Advanced Usage Examples

## Custom Storage with PSR-6 Cache

You can use any PSR-6 compatible cache library for persistent storage:

```php
<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Storage\StorageInterface;
use Psr\Cache\CacheItemPoolInterface;

// Implement PSR-6 cache storage
class PSR6CacheStorage implements StorageInterface
{
    private CacheItemPoolInterface $cache;
    private string $prefix;

    public function __construct(CacheItemPoolInterface $cache, string $prefix = 'circuit_breaker_')
    {
        $this->cache = $cache;
        $this->prefix = $prefix;
    }

    public function load(string $serviceKey): ?array
    {
        $cacheKey = $this->prefix . $serviceKey;
        $item = $this->cache->getItem($cacheKey);

        if (!$item->isHit()) {
            return null;
        }

        return $item->get();
    }

    public function save(string $serviceKey, array $data): void
    {
        $cacheKey = $this->prefix . $serviceKey;
        $item = $this->cache->getItem($cacheKey);
        $item->set($data);
        $this->cache->save($item);
    }
}

// Usage with Symfony Cache
$cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter();
$storage = new PSR6CacheStorage($cache);
$breaker = new CircuitBreaker('api-service', [], $storage);
```

## Using with HTTP Clients

### With Guzzle

```php
<?php

use Farzai\Breaker\CircuitBreaker;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$client = new Client();
$breaker = new CircuitBreaker('api-service');

try {
    $response = $breaker->call(function() use ($client) {
        return $client->get('https://api.example.com/data');
    });
    
    $data = json_decode($response->getBody(), true);
    // Process $data
} catch (\Farzai\Breaker\Exceptions\CircuitOpenException $e) {
    // Circuit is open, use fallback
    $data = getFromFallbackSource();
} catch (RequestException $e) {
    // Handle request error
    $data = getFromFallbackSource();
}
```

## Fallback Strategies

```php
<?php

use Farzai\Breaker\CircuitBreaker;
use Farzai\Breaker\Exceptions\CircuitOpenException;

$breaker = new CircuitBreaker('api-service');

function getDataWithFallback($breaker, $primaryFn, $fallbackFn) {
    try {
        return $breaker->call($primaryFn);
    } catch (CircuitOpenException $e) {
        // Circuit is open, use fallback directly
        return $fallbackFn();
    } catch (\Throwable $e) {
        // Service failed, use fallback
        return $fallbackFn();
    }
}

// Usage
$result = getDataWithFallback(
    $breaker,
    function() {
        // Primary data source
        return callExternalService();
    },
    function() {
        // Fallback data source
        return getFromDatabase();
    }
);
```
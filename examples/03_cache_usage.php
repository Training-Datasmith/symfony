<?php

declare(strict_types=1);

/**
 * Example 3: Cache Component — PSR-6 and PSR-16 cache pool usage
 *
 * Demonstrates the FilesystemAdapter for persistent caching,
 * the ArrayAdapter for in-memory testing, and the PSR-16 wrapper.
 *
 * Usage:
 *   php examples/03_cache_usage.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

// ---------------------------------------------------------------------------
// 1. PSR-6 API — FilesystemAdapter (persistent across requests)
// ---------------------------------------------------------------------------

$pool = new FilesystemAdapter(
    namespace: 'my_app',
    defaultLifetime: 300,           // 5 minutes default TTL
    directory: sys_get_temp_dir() . '/symfony_cache_example',
);

$item = $pool->getItem('user.42.profile');

if (!$item->isHit()) {
    echo '[MISS] Fetching user profile from "database"...' . PHP_EOL;

    // Simulate an expensive database query
    $profile = ['id' => 42, 'name' => 'Jane Doe', 'email' => 'jane@example.com'];

    $item->set($profile);
    $item->expiresAfter(new DateInterval('PT10M')); // 10 minutes
    $pool->save($item);
} else {
    echo '[HIT]  Loaded user profile from cache.' . PHP_EOL;
}

$profile = $item->get();
echo 'User: ' . $profile['name'] . ' <' . $profile['email'] . '>' . PHP_EOL;

// ---------------------------------------------------------------------------
// 2. PSR-16 API — simpler get/set interface via Psr16Cache wrapper
// ---------------------------------------------------------------------------

$simpleCache = new Psr16Cache($pool);

$count = $simpleCache->get('page.views', 0);
$simpleCache->set('page.views', $count + 1, ttl: 60);

echo 'Page views (PSR-16): ' . $simpleCache->get('page.views') . PHP_EOL;

// ---------------------------------------------------------------------------
// 3. ArrayAdapter — deterministic in-memory cache for tests
// ---------------------------------------------------------------------------

$testPool = new ArrayAdapter(defaultLifetime: 60, storeSerialized: false);

$testPool->get('expensive.computation', function (\Symfony\Contracts\Cache\ItemInterface $item): array {
    $item->expiresAfter(30);
    return ['result' => 42];
});

$cached = $testPool->get('expensive.computation', static fn () => ['result' => 0]);
echo 'Cached computation result: ' . $cached['result'] . PHP_EOL;

// ---------------------------------------------------------------------------
// 4. Cache invalidation by key
// ---------------------------------------------------------------------------

$pool->deleteItem('user.42.profile');
echo 'Cache entry deleted.' . PHP_EOL;

// Cleanup
$pool->clear();

<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'cache' => [
        'app' => 'cache.adapter.redis_tag_aware',
    ],
]);

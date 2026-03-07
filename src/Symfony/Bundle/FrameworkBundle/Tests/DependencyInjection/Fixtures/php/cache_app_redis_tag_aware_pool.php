<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'cache' => [
        'app' => 'cache.redis_tag_aware.foo',
        'pools' => [
            'cache.redis_tag_aware.foo' => [
                'adapter' => 'cache.adapter.redis_tag_aware',
            ],
        ],
    ],
]);

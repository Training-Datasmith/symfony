<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'http_client' => [
        'scoped_clients' => [
            'foo' => [
                'scope' => '.*',
            ],
        ],
    ],
]);

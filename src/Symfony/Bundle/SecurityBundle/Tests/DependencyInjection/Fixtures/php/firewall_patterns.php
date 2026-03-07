<?php

declare(strict_types=1);

$container->loadFromExtension('security', [
    'firewalls' => [
        'no_security' => [
            'pattern' => [
                '^/register$',
                '^/documentation$',
            ],
        ],
    ],
]);

<?php

declare(strict_types=1);

$container->loadFromExtension('security', [
    'providers' => [
        'default' => [
            'memory' => [
                'users' => ['foo' => ['password' => 'foo', 'roles' => 'ROLE_USER']],
            ],
        ],
    ],
    'firewalls' => [
        'main' => [
            'form_login' => ['provider' => 'undefined'],
        ],
    ],
]);

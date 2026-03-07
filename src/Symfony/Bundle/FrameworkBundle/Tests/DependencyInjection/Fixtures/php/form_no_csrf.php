<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'form' => [
        'csrf_protection' => [
            'enabled' => false,
        ],
    ],
]);

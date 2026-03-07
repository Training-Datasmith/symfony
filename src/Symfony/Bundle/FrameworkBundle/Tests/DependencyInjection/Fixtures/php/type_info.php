<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'type_info' => [
        'enabled' => true,
        'aliases' => [
            'CustomAlias' => 'int',
        ],
    ],
]);

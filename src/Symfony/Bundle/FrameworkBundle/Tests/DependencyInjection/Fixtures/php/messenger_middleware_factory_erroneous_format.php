<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'messenger' => [
        'buses' => [
            'command_bus' => [
                'middleware' => [
                    [
                        'foo' => ['qux'],
                        'bar' => ['baz'],
                    ],
                ],
            ],
        ],
    ],
]);

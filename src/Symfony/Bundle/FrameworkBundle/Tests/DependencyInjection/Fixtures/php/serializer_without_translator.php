<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'serializer' => [
        'enabled' => true,
    ],
    'translator' => [
        'enabled' => false,
    ],
]);

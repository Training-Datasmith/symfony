<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'profiler' => [
        'enabled' => true,
    ],
    'serializer' => [
        'enabled' => true,
    ],
]);

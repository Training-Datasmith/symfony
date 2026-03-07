<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'fragments' => [
        'enabled' => false,
    ],
    'esi' => [
        'enabled' => true,
    ],
    'ssi' => [
        'enabled' => true,
    ],
]);

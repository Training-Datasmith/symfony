<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'secret' => 's3cr3t',
    'validation' => [
        'enabled' => true,
        'static_method' => false,
    ],
]);

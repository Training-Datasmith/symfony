<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'csrf_protection' => [
        'enabled' => true,
    ],
]);

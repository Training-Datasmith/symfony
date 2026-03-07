<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'csrf_protection' => false,
    'form' => [
        'csrf_protection' => true,
    ],
]);

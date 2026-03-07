<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'php_errors' => [
        'log' => false,
        'throw' => false,
    ],
]);

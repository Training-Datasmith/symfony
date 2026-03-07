<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'php_errors' => [
        'log' => [
            \E_NOTICE => \Psr\Log\LogLevel::ERROR,
            \E_WARNING => \Psr\Log\LogLevel::ERROR,
        ],
    ],
]);

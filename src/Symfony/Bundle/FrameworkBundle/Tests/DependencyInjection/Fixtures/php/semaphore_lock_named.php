<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'lock' => [
        'default' => 'flock',
        'foo' => 'flock',
    ],
    'semaphore' => [
        'default' => 'lock://',
        'bar' => 'lock://foo',
    ],
]);

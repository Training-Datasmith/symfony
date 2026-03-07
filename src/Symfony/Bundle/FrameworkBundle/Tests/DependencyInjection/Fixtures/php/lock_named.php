<?php

declare(strict_types=1);

$container->setParameter('env(REDIS_DSN)', 'redis://paas.com');

$container->loadFromExtension('framework', [
    'lock' => [
        'foo' => 'semaphore',
        'bar' => 'flock',
        'baz' => ['semaphore', 'flock'],
        'qux' => '%env(REDIS_DSN)%',
        'corge' => 'in-memory',
        'grault' => 'mysql:host=localhost;dbname=test',
        'garply' => 'null',
    ],
]);

<?php

declare(strict_types=1);

$container->register('my_service', \Redis::class);

$container->loadFromExtension('framework', [
    'lock' => 'my_service',
]);

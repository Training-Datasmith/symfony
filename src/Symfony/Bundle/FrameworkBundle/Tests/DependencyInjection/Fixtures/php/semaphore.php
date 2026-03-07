<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'semaphore' => 'redis://localhost',
]);

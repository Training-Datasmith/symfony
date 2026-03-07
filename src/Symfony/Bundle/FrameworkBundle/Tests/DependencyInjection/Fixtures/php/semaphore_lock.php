<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'lock' => 'flock',
    'semaphore' => 'lock://',
]);

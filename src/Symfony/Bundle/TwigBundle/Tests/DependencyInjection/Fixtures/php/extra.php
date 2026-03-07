<?php

declare(strict_types=1);

$container->loadFromExtension('twig', [
    'paths' => [
        'namespaced_path3' => 'namespace3',
    ],
]);

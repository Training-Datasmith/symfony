<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'message_bus' => 'app.another_bus',
    ],
]);

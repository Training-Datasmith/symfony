<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'mailer' => [
        'dsn' => 'smtp://example.com',
        'envelope' => [
            'sender' => 'sender@example.org',
            'recipients' => ['redirected@example.org', 'redirected1@example.org'],
        ],
        'headers' => [
            'from' => 'from@example.org',
            'bcc' => ['bcc1@example.org', 'bcc2@example.org'],
            'foo' => 'bar',
        ],
    ],
]);

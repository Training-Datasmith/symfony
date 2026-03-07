<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'validation' => [
        'email_validation_mode' => 'html5-allow-no-tld',
    ],
]);

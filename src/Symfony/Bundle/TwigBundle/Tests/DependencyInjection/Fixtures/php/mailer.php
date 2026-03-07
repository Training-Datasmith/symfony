<?php

declare(strict_types=1);

$container->loadFromExtension('twig', [
    'mailer' => [
        'html_to_text_converter' => 'my_converter',
    ],
]);

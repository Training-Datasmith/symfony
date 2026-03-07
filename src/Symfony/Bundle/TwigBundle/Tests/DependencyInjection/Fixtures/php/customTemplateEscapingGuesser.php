<?php

declare(strict_types=1);

$container->loadFromExtension('twig', [
    'autoescape_service' => 'my_project.some_bundle.template_escaping_guesser',
    'autoescape_service_method' => 'guess',
]);

<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'property_info' => [
        'enabled' => true,
        'with_constructor_extractor' => false,
    ],
]);

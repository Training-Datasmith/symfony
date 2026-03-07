<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'webhook' => ['enabled' => true],
    'http_client' => ['enabled' => true],
    'serializer' => ['enabled' => false],
]);

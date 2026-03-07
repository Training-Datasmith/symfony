<?php

declare(strict_types=1);

$container->loadFromExtension('framework', [
    'trusted_proxies' => 'private_ranges',
]);

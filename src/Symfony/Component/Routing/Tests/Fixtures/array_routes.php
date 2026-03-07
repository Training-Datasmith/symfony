<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\Routes;

return Routes::config([
    'a' => ['path' => '/a'],
    'b' => ['path' => '/b', 'methods' => ['GET']],
]);

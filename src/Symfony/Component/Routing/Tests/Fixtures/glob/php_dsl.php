<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return fn (RoutingConfigurator $routes) => $routes->import('php_dsl_ba?.php');

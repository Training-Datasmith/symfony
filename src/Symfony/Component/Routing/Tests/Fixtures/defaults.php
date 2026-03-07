<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes->add('defaults', '/defaults')
        ->locale('en')
        ->format('html')
        ->stateless(true)
    ;
};

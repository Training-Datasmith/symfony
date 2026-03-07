<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes
        ->add('some_route', '/')
        ->add('some_utf8_route', '/utf8')->utf8()
    ;
};

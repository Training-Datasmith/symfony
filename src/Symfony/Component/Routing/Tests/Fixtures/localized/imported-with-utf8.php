<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $routes
        ->add('utf8_one', '/one')
        ->add('utf8_two', '/two')
    ;
};

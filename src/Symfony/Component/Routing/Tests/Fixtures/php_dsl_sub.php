<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

return function (RoutingConfigurator $routes) {
    $add = $routes->collection('c_')
        ->prefix('pub');

    $add('root', '/');
    $add('bar', '/bar');

    $add->collection('pub_')
        ->host('host')
        ->add('buz', 'buz');
};

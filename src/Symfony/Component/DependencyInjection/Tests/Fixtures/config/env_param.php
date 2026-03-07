<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container, string $env) {
    $container->extension('acme', [
        'color' => 'prod' === $env ? 'blue' : 'red',
    ]);
};

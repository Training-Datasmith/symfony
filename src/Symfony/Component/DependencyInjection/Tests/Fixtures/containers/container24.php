<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container
    ->register('foo', 'Foo')
    ->setAutowired(true)
    ->setPublic(true)
;

return $container;

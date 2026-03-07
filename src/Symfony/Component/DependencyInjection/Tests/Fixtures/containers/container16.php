<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$container
    ->register('foo', 'FooClass\\Foo')
    ->setDecoratedService('bar')
    ->setPublic(true)
;

return $container;

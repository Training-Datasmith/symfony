<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$container->
    register('foo', 'FooClass\\Foo')->
    addArgument('foo<>&bar')->
    addTag('foo"bar\\bar', ['foo' => 'foo"barřž€'])
    ->setPublic(true)
;

return $container;

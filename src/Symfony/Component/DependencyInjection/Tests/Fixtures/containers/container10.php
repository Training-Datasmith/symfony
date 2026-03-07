<?php

declare(strict_types=1);

require_once __DIR__.'/../includes/classes.php';

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();
$container->
    register('foo', 'FooClass')->
    addArgument(new Reference('bar'))
    ->setPublic(true)
;

return $container;

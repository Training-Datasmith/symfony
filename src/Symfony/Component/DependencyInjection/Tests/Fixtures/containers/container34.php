<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

$container = new ContainerBuilder();
$container
    ->register('decorator')
    ->setDecoratedService('decorated', 'decorated.inner', 1, ContainerInterface::NULL_ON_INVALID_REFERENCE)
;

return $container;

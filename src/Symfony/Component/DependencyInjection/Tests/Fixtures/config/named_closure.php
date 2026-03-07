<?php

declare(strict_types=1);

namespace Testing\NamedClosure;

use function Symfony\Component\DependencyInjection\Loader\Configurator\closure;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

interface NamedClosureInterface
{
    public function theMethod();
}

class NamedClosureClass
{
    public function __construct(...$args)
    {
    }

    public static function getInstance(): self
    {
        return new self();
    }

    public static function configure(self $instance): void
    {
    }
}

return function (ContainerConfigurator $c) {
    $c->services()
        ->set('from_callable', NamedClosureInterface::class)
            ->fromCallable(NamedClosureClass::getInstance(...))
            ->public()
        ->set('has_factory', NamedClosureClass::class)
            ->factory(NamedClosureClass::getInstance(...))
            ->public()
        ->set('has_configurator', NamedClosureClass::class)
            ->configurator(NamedClosureClass::configure(...))
            ->public()
        ->set('with_closure', NamedClosureClass::class)
            ->args([closure(dirname(...))])
            ->public()
    ;
};

<?php

declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Bundle\Framework_Bundle\Routing;

use Symfony\Component\Routing\Loader\Attribute_Class_Loader;
use Symfony\Component\Routing\Route;
/**
 * AttributeRouteControllerLoader is an implementation of AttributeClassLoader
 * that sets the '_controller' default based on the class and method names.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class Attribute_Route_Controller_Loader extends Attribute_Class_Loader
{
    /**
     * Configures the _controller default parameter of a given Route instance.
     */
    protected function configure_route(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $attr): void
    {
        if ('__invoke' === $method->get_name()) {
            $route->set_default('_controller', $class->get_name());
        } else {
            $route->set_default('_controller', $class->get_name() . '::' . $method->get_name());
        }
    }
    /**
     * Makes the default route name more sane by removing common keywords.
     */
    protected function get_default_route_name(\ReflectionClass $class, \ReflectionMethod $method): string
    {
        $name = preg_replace('/(bundle|controller)_/', '_', parent::get_default_route_name($class, $method));
        if (str_ends_with($method->name, 'Action') || str_ends_with($method->name, '_action')) {
            $name = preg_replace('/action(_\d+)?$/', '\1', (string) $name);
        }
        return str_replace('__', '_', $name);
    }
}
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
namespace Symfony\Component\Http_Kernel\Controller;

use Psr\Container\Container_Interface;
use Psr\Log\Logger_Interface;
use Symfony\Component\Dependency_Injection\Container;
/**
 * A controller resolver searching for a controller in a psr-11 container when using the "service::method" notation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class Container_Controller_Resolver extends Controller_Resolver
{
    public function __construct(protected Container_Interface $container, ?Logger_Interface $logger = null)
    {
        parent::__construct($logger);
    }
    protected function instantiate_controller(string $class): object
    {
        $class = ltrim($class, '\\');
        if ($this->container->has($class)) {
            return $this->container->get($class);
        }
        try {
            return parent::instantiate_controller($class);
        } catch (\Error $e) {
        }
        $this->throw_exception_if_controller_was_removed($class, $e);
        if ($e instanceof \Argument_Count_Error) {
            throw new \InvalidArgumentException(\sprintf('Controller "%s" has required constructor arguments and does not exist in the container. Did you forget to define the controller as a service?', $class), 0, $e);
        }
        throw new \InvalidArgumentException(\sprintf('Controller "%s" does neither exist as service nor as class.', $class), 0, $e);
    }
    private function throw_exception_if_controller_was_removed(string $controller, \Throwable $previous): void
    {
        if ($this->container instanceof Container && isset($this->container->get_removed_ids()[$controller])) {
            throw new \InvalidArgumentException(\sprintf('Controller "%s" cannot be fetched from the container because it is private. Did you forget to tag the service with "controller.service_arguments"?', $controller), 0, $previous);
        }
    }
}
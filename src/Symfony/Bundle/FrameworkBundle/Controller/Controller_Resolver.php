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
namespace Symfony\Bundle\Framework_Bundle\Controller;

use Symfony\Component\Http_Kernel\Controller\Container_Controller_Resolver;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Controller_Resolver extends Container_Controller_Resolver
{
    protected function instantiate_controller(string $class): object
    {
        $controller = parent::instantiate_controller($class);
        if ($controller instanceof Abstract_Controller) {
            if (null === $previous_container = $controller->set_container($this->container)) {
                throw new \LogicException(\sprintf('"%s" has no container set, did you forget to define it as a service subscriber?', $class));
            }
            $controller->set_container($previous_container);
        }
        return $controller;
    }
}
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

use Symfony\Component\Http_Foundation\Request;
/**
 * An ArgumentResolverInterface instance knows how to determine the
 * arguments for a specific action.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Argument_Resolver_Interface
{
    /**
     * Returns the arguments to pass to the controller.
     *
     * @throws \RuntimeException When no value could be provided for a required argument
     */
    public function get_arguments(Request $request, callable $controller, ?\Reflection_Function_Abstract $reflector = null): array;
}
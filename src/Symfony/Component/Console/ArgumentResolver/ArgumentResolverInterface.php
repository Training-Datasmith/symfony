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
namespace Symfony\Component\Console\Argument_Resolver;

use Symfony\Component\Console\Argument_Resolver\Exception\Resolver_Not_Found_Exception;
use Symfony\Component\Console\Input\Input_Interface;
/**
 * Determines the arguments for a specific Console Command.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface Argument_Resolver_Interface
{
    /**
     * Returns the arguments to pass to the Console Command after resolution.
     *
     * @throws \RuntimeException         When no value could be provided for a required argument
     * @throws ResolverNotFoundException
     */
    public function get_arguments(Input_Interface $input, callable $command, ?\Reflection_Function_Abstract $reflector = null): array;
}
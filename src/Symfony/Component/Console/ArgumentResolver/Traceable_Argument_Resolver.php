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

use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class Traceable_Argument_Resolver implements Argument_Resolver_Interface
{
    public function __construct(private readonly Argument_Resolver_Interface $resolver, private readonly Stopwatch $stopwatch)
    {
    }
    public function get_arguments(Input_Interface $input, callable $command, ?\Reflection_Function_Abstract $reflector = null): array
    {
        $e = $this->stopwatch->start('command.get_arguments');
        try {
            return $this->resolver->get_arguments($input, $command, $reflector);
        } finally {
            $e->stop();
        }
    }
}
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
namespace Symfony\Component\Console\Argument_Resolver\Value_Resolver;

use Symfony\Component\Console\Attribute\Reflection\Reflection_Member;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final readonly class Traceable_Value_Resolver implements Value_Resolver_Interface
{
    public function __construct(private Value_Resolver_Interface $inner, private Stopwatch $stopwatch)
    {
    }
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable
    {
        $method = $this->inner::class . '::' . __FUNCTION__;
        $this->stopwatch->start($method, 'command.argument_value_resolver');
        yield from $this->inner->resolve($argument_name, $input, $member);
        $this->stopwatch->stop($method);
    }
}
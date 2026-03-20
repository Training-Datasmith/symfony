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
namespace Symfony\Component\Http_Kernel\Controller\Argument_Resolver;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * Provides timing information via the stopwatch.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final readonly class Traceable_Value_Resolver implements Value_Resolver_Interface
{
    public function __construct(private Value_Resolver_Interface $inner, private Stopwatch $stopwatch)
    {
    }
    public function resolve(Request $request, Argument_Metadata $argument): iterable
    {
        $method = $this->inner::class . '::' . __FUNCTION__;
        $this->stopwatch->start($method, 'controller.argument_value_resolver');
        yield from $this->inner->resolve($request, $argument);
        $this->stopwatch->stop($method);
    }
}
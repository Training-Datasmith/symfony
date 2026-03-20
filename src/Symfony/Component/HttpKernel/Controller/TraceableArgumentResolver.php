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
use Symfony\Component\Stopwatch\Stopwatch;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Traceable_Argument_Resolver implements Argument_Resolver_Interface
{
    public function __construct(private readonly Argument_Resolver_Interface $resolver, private readonly Stopwatch $stopwatch)
    {
    }
    public function get_arguments(Request $request, callable $controller, ?\Reflection_Function_Abstract $reflector = null): array
    {
        $e = $this->stopwatch->start('controller.get_arguments');
        try {
            return $this->resolver->get_arguments($request, $controller, $reflector);
        } finally {
            $e->stop();
        }
    }
}
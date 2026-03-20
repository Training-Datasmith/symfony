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
namespace Symfony\Component\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Expression_Language\Expression_Function;
use Symfony\Component\Expression_Language\Expression_Function_Provider_Interface;
/**
 * Define some ExpressionLanguage functions.
 *
 * To get a service, use service('request').
 * To get a parameter, use parameter('kernel.debug').
 * To get an env variable, use env('SOME_VARIABLE').
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Expression_Language_Provider implements Expression_Function_Provider_Interface
{
    private readonly ?\Closure $service_compiler;
    public function __construct(?callable $service_compiler = null, private readonly ?\Closure $get_env = null)
    {
        $this->service_compiler = null === $service_compiler ? null : $service_compiler(...);
    }
    public function get_functions(): array
    {
        return [new Expression_Function('service', $this->service_compiler ?? static fn(string $arg): string => \sprintf('$container->get(%s)', $arg), static fn(array $variables, $value) => $variables['container']->get($value)), new Expression_Function('parameter', static fn(string $arg): string => \sprintf('$container->getParameter(%s)', $arg), static fn(array $variables, $value) => $variables['container']->get_parameter($value)), new Expression_Function('env', static fn(string $arg): string => \sprintf('$container->getEnv(%s)', $arg), function (array $variables, $value) {
            if (!$this->get_env) {
                throw new LogicException('You need to pass a getEnv closure to the expression language provider to use the "env" function.');
            }
            return ($this->get_env)($value);
        }), new Expression_Function('arg', static fn(string $arg): string => \sprintf('$args?->get(%s)', $arg), static fn(array $variables, $value) => $variables['args']?->get($value))];
    }
}
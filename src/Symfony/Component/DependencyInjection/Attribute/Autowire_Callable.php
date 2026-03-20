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
namespace Symfony\Component\Dependency_Injection\Attribute;

use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Attribute to tell which callable to give to an argument of type Closure.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Autowire_Callable extends Autowire_Inline
{
    /**
     * @param string|array|null $callable The callable to autowire
     * @param string|null       $service  The service containing the callable to autowire
     * @param string|null       $method   The method name that will be autowired
     * @param bool|class-string $lazy     Whether to use lazy-loading for this argument
     */
    public function __construct(string|array|null $callable = null, ?string $service = null, ?string $method = null, bool|string $lazy = false)
    {
        if (!(null !== $callable xor null !== $service)) {
            throw new LogicException('#[AutowireCallable] attribute must declare exactly one of $callable or $service.');
        }
        if (null === $service && null !== $method) {
            throw new LogicException('#[AutowireCallable] attribute cannot have a $method without a $service.');
        }
        Autowire::__construct($callable ?? [new Reference($service), $method ?? '__invoke'], lazy: $lazy);
    }
    public function build_definition(mixed $value, ?string $type, \ReflectionParameter $parameter): Definition
    {
        return (new Definition($type = \is_array($this->lazy) ? current($this->lazy) : ($type ?: 'Closure')))->set_factory(['Closure', 'fromCallable'])->set_arguments([\is_array($value) ? $value + [1 => '__invoke'] : $value])->set_lazy($this->lazy || 'Closure' !== $type && 'callable' !== (string) $parameter->get_type());
    }
}
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
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Tells which method should be turned into a Closure based on the name of the parameter it's attached to.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Autowire_Method_Of extends Autowire_Callable
{
    /**
     * @param string            $service The service containing the method to autowire
     * @param bool|class-string $lazy    Whether to use lazy-loading for this argument
     */
    public function __construct(string $service, bool|string $lazy = false)
    {
        parent::__construct([new Reference($service)], lazy: $lazy);
    }
    public function build_definition(mixed $value, ?string $type, \ReflectionParameter $parameter): Definition
    {
        $value[1] = $parameter->name;
        return parent::build_definition($value, $type, $parameter);
    }
}
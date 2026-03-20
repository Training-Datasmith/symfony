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
namespace Symfony\Component\Dependency_Injection\Exception;

/**
 * Thrown when trying to inject a parameter into a constructor/method with an incompatible type.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Julien Maulny <jmaulny@darkmira.fr>
 */
class Invalid_Parameter_Type_Exception extends InvalidArgumentException
{
    public function __construct(string $service_id, string $type, \ReflectionParameter $parameter)
    {
        $accepted_type = $parameter->get_type();
        $accepted_type = $accepted_type instanceof \ReflectionNamedType ? $accepted_type->get_name() : (string) $accepted_type;
        $this->code = $type;
        $function = $parameter->get_declaring_function();
        $function_name = $function instanceof \ReflectionMethod ? \sprintf('%s::%s', $function->class, $function->get_name()) : $function->get_name();
        parent::__construct(\sprintf('Invalid definition for service "%s": argument %d of "%s()" accepts "%s", "%s" passed.', $service_id, 1 + $parameter->get_position(), $function_name, $accepted_type, $type));
    }
}
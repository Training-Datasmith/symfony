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
namespace Symfony\Component\Dependency_Injection\Parameter_Bag;

use Psr\Container\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\Parameter_Not_Found_Exception;
/**
 * ContainerBagInterface is the interface implemented by objects that manage service container parameters.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface Container_Bag_Interface extends Container_Interface
{
    /**
     * Gets the service container parameters.
     */
    public function all(): array;
    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @template TValue of array<array|scalar>|scalar
     *
     * @param TValue $value
     *
     * @psalm-return (TValue is scalar ? array|scalar : array<array|scalar>)
     *
     * @throws ParameterNotFoundException if a placeholder references a parameter that does not exist
     */
    public function resolve_value(mixed $value): mixed;
    /**
     * Escape parameter placeholders %.
     */
    public function escape_value(mixed $value): mixed;
    /**
     * Unescape parameter placeholders %.
     */
    public function unescape_value(mixed $value): mixed;
}
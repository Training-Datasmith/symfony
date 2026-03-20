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

use Symfony\Component\Dependency_Injection\Exception\LogicException;
/**
 * Holds read-only parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Frozen_Parameter_Bag extends Parameter_Bag
{
    /**
     * For performance reasons, the constructor assumes that
     * all keys are already lowercased.
     *
     * This is always the case when used internally.
     */
    public function __construct(array $parameters = [], protected array $deprecated_parameters = [], protected array $non_empty_parameters = [])
    {
        $this->parameters = $parameters;
        $this->resolved = true;
    }
    public function clear(): never
    {
        throw new LogicException('Impossible to call clear() on a frozen ParameterBag.');
    }
    public function add(array $parameters): never
    {
        throw new LogicException('Impossible to call add() on a frozen ParameterBag.');
    }
    public function set(string $name, array|bool|string|int|float|\Unit_Enum|null $value): never
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }
    public function deprecate(string $name, string $package, string $version, string $message = 'The parameter "%s" is deprecated.'): never
    {
        throw new LogicException('Impossible to call deprecate() on a frozen ParameterBag.');
    }
    public function cannot_be_empty(string $name, string $message = 'A non-empty parameter "%s" is required.'): never
    {
        throw new LogicException('Impossible to call cannotBeEmpty() on a frozen ParameterBag.');
    }
    public function remove(string $name): never
    {
        throw new LogicException('Impossible to call remove() on a frozen ParameterBag.');
    }
}
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
namespace Symfony\Component\Dependency_Injection\Argument;

use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
/**
 * Represents a service wrapped in a memoizing closure.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Service_Closure_Argument implements Argument_Interface
{
    use Argument_Trait;
    private array $values;
    public function __construct(mixed $value)
    {
        $this->values = [$value];
    }
    public function get_values(): array
    {
        return $this->values;
    }
    public function set_values(array $values): void
    {
        if ([0] !== array_keys($values)) {
            throw new InvalidArgumentException('A ServiceClosureArgument must hold one and only one value.');
        }
        $this->values = $values;
    }
}
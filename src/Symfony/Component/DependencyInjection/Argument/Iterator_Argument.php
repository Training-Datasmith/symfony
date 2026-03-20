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

/**
 * Represents a collection of values to lazily iterate over.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class Iterator_Argument implements Argument_Interface
{
    use Argument_Trait;
    private array $values;
    public function __construct(array $values)
    {
        $this->set_values($values);
    }
    public function get_values(): array
    {
        return $this->values;
    }
    public function set_values(array $values): void
    {
        $this->values = $values;
    }
}
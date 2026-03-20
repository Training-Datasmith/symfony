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

use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\OutOfBoundsException;
/**
 * This definition extends another definition.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Child_Definition extends Definition
{
    /**
     * @param string $parent The id of Definition instance to decorate
     */
    public function __construct(private string $parent)
    {
    }
    /**
     * Returns the Definition to inherit from.
     */
    public function get_parent(): string
    {
        return $this->parent;
    }
    /**
     * Sets the Definition to inherit from.
     *
     * @return $this
     */
    public function set_parent(string $parent): static
    {
        $this->parent = $parent;
        return $this;
    }
    /**
     * Gets an argument to pass to the service constructor/factory method.
     *
     * If replaceArgument() has been used to replace an argument, this method
     * will return the replacement value.
     *
     * @throws OutOfBoundsException When the argument does not exist
     */
    public function get_argument(int|string $index): mixed
    {
        if (\array_key_exists('index_' . $index, $this->arguments)) {
            return $this->arguments['index_' . $index];
        }
        return parent::get_argument($index);
    }
    /**
     * You should always use this method when overwriting existing arguments
     * of the parent definition.
     *
     * If you directly call setArguments() keep in mind that you must follow
     * certain conventions when you want to overwrite the arguments of the
     * parent definition, otherwise your arguments will only be appended.
     *
     * @return $this
     *
     * @throws InvalidArgumentException when $index isn't an integer
     */
    public function replace_argument(int|string $index, mixed $value): static
    {
        if (\is_int($index)) {
            $this->arguments['index_' . $index] = $value;
        } elseif (str_starts_with($index, '$')) {
            $this->arguments[$index] = $value;
        } else {
            throw new InvalidArgumentException('The argument must be an existing index or the name of a constructor\'s parameter.');
        }
        return $this;
    }
}
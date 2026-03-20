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
 * Represents a closure acting as a service locator.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Service_Locator_Argument implements Argument_Interface
{
    use Argument_Trait;
    private array $values;
    private ?Tagged_Iterator_Argument $tagged_iterator_argument = null;
    public function __construct(array|Tagged_Iterator_Argument $values = [])
    {
        if ($values instanceof Tagged_Iterator_Argument) {
            $this->tagged_iterator_argument = $values;
            $values = [];
        }
        $this->set_values($values);
    }
    public function get_tagged_iterator_argument(): ?Tagged_Iterator_Argument
    {
        return $this->tagged_iterator_argument;
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
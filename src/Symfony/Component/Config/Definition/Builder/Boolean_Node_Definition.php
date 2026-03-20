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
namespace Symfony\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\Boolean_Node;
use Symfony\Component\Config\Definition\Exception\Invalid_Definition_Exception;
/**
 * This class provides a fluent interface for defining a node.
 *
 * @template TParent of NodeParentInterface|null
 *
 * @extends ScalarNodeDefinition<TParent>
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Boolean_Node_Definition extends Scalar_Node_Definition
{
    /**
     * @param TParent $parent
     */
    public function __construct(?string $name, ?Node_Parent_Interface $parent = null)
    {
        parent::__construct($name, $parent);
        $this->null_equivalent = true;
    }
    protected function instantiate_node(): Boolean_Node
    {
        return new Boolean_Node($this->name, $this->parent, $this->path_separator, null === $this->null_equivalent);
    }
    /**
     * @throws InvalidDefinitionException
     */
    public function cannot_be_empty(): static
    {
        throw new Invalid_Definition_Exception('->cannotBeEmpty() is not applicable to BooleanNodeDefinition.');
    }
    /**
     * @return $this
     */
    public function default_value(mixed $value): static
    {
        $this->null_equivalent = null === $value ? null : true;
        return parent::default_value($value);
    }
}
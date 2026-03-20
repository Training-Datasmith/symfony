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

use Symfony\Component\Config\Definition\Enum_Node;
/**
 * Enum Node Definition.
 *
 * @template TParent of NodeParentInterface|null
 *
 * @extends ScalarNodeDefinition<TParent>
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Enum_Node_Definition extends Scalar_Node_Definition
{
    private array $values;
    private string $enum_fqcn;
    /**
     * @return $this
     */
    public function values(array $values): static
    {
        if (!$values) {
            throw new \InvalidArgumentException('->values() must be called with at least one value.');
        }
        $this->values = $values;
        return $this;
    }
    /**
     * @param class-string<\UnitEnum> $enumFqcn
     *
     * @return $this
     */
    public function enum_fqcn(string $enum_fqcn): static
    {
        if (!enum_exists($enum_fqcn)) {
            throw new \InvalidArgumentException(\sprintf('The enum class "%s" does not exist.', $enum_fqcn));
        }
        $this->enum_fqcn = $enum_fqcn;
        return $this;
    }
    /**
     * @throws \RuntimeException when no values or enumFqcn is set
     */
    protected function instantiate_node(): Enum_Node
    {
        if (!isset($this->values) && !isset($this->enum_fqcn)) {
            throw new \RuntimeException('You must call either ->values() or ->enumFqcn() on enum nodes.');
        }
        if (isset($this->values) && isset($this->enum_fqcn)) {
            throw new \RuntimeException('You must call either ->values() or ->enumFqcn() on enum nodes but not both.');
        }
        return new Enum_Node($this->name, $this->parent, $this->values ?? [], $this->path_separator, $this->enum_fqcn ?? null);
    }
}
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

/**
 * This class builds merge conditions.
 *
 * @template T of NodeDefinition
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Merge_Builder
{
    public bool $allow_false = false;
    public bool $allow_overwrite = true;
    /**
     * @param T $node
     */
    public function __construct(protected Node_Definition $node)
    {
    }
    /**
     * Sets whether the node can be unset.
     *
     * @return $this
     */
    public function allow_unset(bool $allow = true): static
    {
        $this->allow_false = $allow;
        return $this;
    }
    /**
     * Sets whether the node can be overwritten.
     *
     * @return $this
     */
    public function deny_overwrite(bool $deny = true): static
    {
        $this->allow_overwrite = !$deny;
        return $this;
    }
    /**
     * Returns the related node.
     *
     * @return T
     */
    public function end(): Node_Definition
    {
        return $this->node;
    }
}
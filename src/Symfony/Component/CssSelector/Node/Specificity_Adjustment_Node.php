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
namespace Symfony\Component\Css_Selector\Node;

/**
 * Represents a "<selector>:where(<subSelectorList>)" node.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Hubert Lenoir <lenoir.hubert@gmail.com>
 *
 * @internal
 */
class Specificity_Adjustment_Node extends Abstract_Node
{
    /**
     * @param array<NodeInterface> $arguments
     */
    public function __construct(public readonly Node_Interface $selector, public readonly array $arguments = [])
    {
    }
    public function get_specificity(): Specificity
    {
        return $this->selector->get_specificity();
    }
    public function __toString(): string
    {
        $selector_arguments = array_map(static fn(\Symfony\Component\Css_Selector\Node\Node_Interface $n): string => ltrim((string) $n, '*'), $this->arguments);
        return \sprintf('%s[%s:where(%s)]', $this->get_node_name(), $this->selector, implode(', ', $selector_arguments));
    }
}
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
 * Represents a "<selector>:has(<subselector>)" node.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/scrapy/cssselect.
 *
 * @author Franck Ranaivo-Harisoa <franckranaivo@gmail.com>
 *
 * @internal
 */
class Relation_Node extends Abstract_Node
{
    public function __construct(private readonly Node_Interface $selector, private readonly string $combinator, private readonly Node_Interface $sub_selector)
    {
    }
    public function get_selector(): Node_Interface
    {
        return $this->selector;
    }
    public function get_combinator(): string
    {
        return $this->combinator;
    }
    public function get_sub_selector(): Node_Interface
    {
        return $this->sub_selector;
    }
    public function get_specificity(): Specificity
    {
        return $this->selector->get_specificity()->plus($this->sub_selector->get_specificity());
    }
    public function __toString(): string
    {
        return \sprintf('%s[%s:has(%s)]', $this->get_node_name(), $this->selector, $this->sub_selector);
    }
}
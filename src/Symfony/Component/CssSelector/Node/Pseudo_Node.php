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
 * Represents a "<selector>:<identifier>" node.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Pseudo_Node extends Abstract_Node
{
    private readonly string $identifier;
    public function __construct(private readonly Node_Interface $selector, string $identifier)
    {
        $this->identifier = strtolower($identifier);
    }
    public function get_selector(): Node_Interface
    {
        return $this->selector;
    }
    public function get_identifier(): string
    {
        return $this->identifier;
    }
    public function get_specificity(): Specificity
    {
        return $this->selector->get_specificity()->plus(new Specificity(0, 1, 0));
    }
    public function __toString(): string
    {
        return \sprintf('%s[%s:%s]', $this->get_node_name(), $this->selector, $this->identifier);
    }
}
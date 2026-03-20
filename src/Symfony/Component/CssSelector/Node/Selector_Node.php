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
 * Represents a "<selector>(::|:)<pseudoElement>" node.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Selector_Node extends Abstract_Node
{
    private readonly ?string $pseudo_element;
    public function __construct(private readonly Node_Interface $tree, ?string $pseudo_element = null)
    {
        $this->pseudo_element = $pseudo_element ? strtolower($pseudo_element) : null;
    }
    public function get_tree(): Node_Interface
    {
        return $this->tree;
    }
    public function get_pseudo_element(): ?string
    {
        return $this->pseudo_element;
    }
    public function get_specificity(): Specificity
    {
        return $this->tree->get_specificity()->plus(new Specificity(0, 0, $this->pseudo_element ? 1 : 0));
    }
    public function __toString(): string
    {
        return \sprintf('%s[%s%s]', $this->get_node_name(), $this->tree, $this->pseudo_element ? '::' . $this->pseudo_element : '');
    }
}
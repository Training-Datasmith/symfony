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
 * Represents a "<namespace>|<element>" node.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Element_Node extends Abstract_Node
{
    public function __construct(private readonly ?string $namespace = null, private readonly ?string $element = null)
    {
    }
    public function get_namespace(): ?string
    {
        return $this->namespace;
    }
    public function get_element(): ?string
    {
        return $this->element;
    }
    public function get_specificity(): Specificity
    {
        return new Specificity(0, 0, $this->element ? 1 : 0);
    }
    public function __toString(): string
    {
        $element = $this->element ?: '*';
        return \sprintf('%s[%s]', $this->get_node_name(), $this->namespace ? $this->namespace . '|' . $element : $element);
    }
}
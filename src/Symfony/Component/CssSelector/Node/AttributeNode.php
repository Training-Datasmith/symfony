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
 * Represents a "<selector>[<namespace>|<attribute> <operator> <value>]" node.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Attribute_Node extends Abstract_Node
{
    public function __construct(private readonly Node_Interface $selector, private readonly ?string $namespace, private readonly string $attribute, private readonly string $operator, private readonly ?string $value)
    {
    }
    public function get_selector(): Node_Interface
    {
        return $this->selector;
    }
    public function get_namespace(): ?string
    {
        return $this->namespace;
    }
    public function get_attribute(): string
    {
        return $this->attribute;
    }
    public function get_operator(): string
    {
        return $this->operator;
    }
    public function get_value(): ?string
    {
        return $this->value;
    }
    public function get_specificity(): Specificity
    {
        return $this->selector->get_specificity()->plus(new Specificity(0, 1, 0));
    }
    public function __toString(): string
    {
        $attribute = $this->namespace ? $this->namespace . '|' . $this->attribute : $this->attribute;
        return 'exists' === $this->operator ? \sprintf('%s[%s[%s]]', $this->get_node_name(), $this->selector, $attribute) : \sprintf("%s[%s[%s %s '%s']]", $this->get_node_name(), $this->selector, $attribute, $this->operator, $this->value);
    }
}
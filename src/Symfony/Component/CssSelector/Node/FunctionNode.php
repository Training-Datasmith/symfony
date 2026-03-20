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

use Symfony\Component\Css_Selector\Parser\Token;
/**
 * Represents a "<selector>:<name>(<arguments>)" node.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Function_Node extends Abstract_Node
{
    private readonly string $name;
    /**
     * @param Token[] $arguments
     */
    public function __construct(private readonly Node_Interface $selector, string $name, private readonly array $arguments = [])
    {
        $this->name = strtolower($name);
    }
    public function get_selector(): Node_Interface
    {
        return $this->selector;
    }
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * @return Token[]
     */
    public function get_arguments(): array
    {
        return $this->arguments;
    }
    public function get_specificity(): Specificity
    {
        return $this->selector->get_specificity()->plus(new Specificity(0, 1, 0));
    }
    public function __toString(): string
    {
        $arguments = implode(', ', array_map(static fn(Token $token): string => "'" . $token->get_value() . "'", $this->arguments));
        return \sprintf('%s[%s:%s(%s)]', $this->get_node_name(), $this->selector, $this->name, $arguments ? '[' . $arguments . ']' : '');
    }
}
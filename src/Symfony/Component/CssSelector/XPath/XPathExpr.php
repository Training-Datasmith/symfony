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
namespace Symfony\Component\Css_Selector\X_Path;

/**
 * XPath expression translator interface.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class X_Path_Expr implements \Stringable
{
    public function __construct(private string $path = '', private string $element = '*', private string $condition = '', bool $star_prefix = false)
    {
        if ($star_prefix) {
            $this->add_star_prefix();
        }
    }
    public function get_element(): string
    {
        return $this->element;
    }
    /**
     * @return $this
     */
    public function add_condition(string $condition, string $operator = 'and'): static
    {
        $this->condition = $this->condition ? \sprintf('(%s) %s (%s)', $this->condition, $operator, $condition) : $condition;
        return $this;
    }
    public function get_condition(): string
    {
        return $this->condition;
    }
    /**
     * @return $this
     */
    public function add_name_test(): static
    {
        if ('*' !== $this->element) {
            $this->add_condition('name() = ' . Translator::get_xpath_literal($this->element));
            $this->element = '*';
        }
        return $this;
    }
    /**
     * @return $this
     */
    public function add_star_prefix(): static
    {
        $this->path .= '*/';
        return $this;
    }
    /**
     * Joins another XPathExpr with a combiner.
     *
     * @return $this
     */
    public function join(string $combiner, self $expr, ?string $closing_combiner = null, bool $has_inner_conditions = false): static
    {
        $path = $this->__toString() . $combiner;
        if ('*/' !== $expr->path) {
            $path .= $expr->path;
        }
        $this->path = $path;
        if (!$has_inner_conditions) {
            $this->element = $expr->element . ($closing_combiner ?? '');
            $this->condition = $expr->condition;
        } else {
            $this->element = $expr->element;
            if ($expr->condition) {
                $this->element .= '[' . $expr->condition . ']';
            }
            if ($closing_combiner) {
                $this->element .= $closing_combiner;
            }
        }
        return $this;
    }
    public function __toString(): string
    {
        $path = $this->path . $this->element;
        $condition = '' === $this->condition ? '' : '[' . $this->condition . ']';
        return $path . $condition;
    }
}
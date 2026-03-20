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
namespace Symfony\Bridge\Twig\Node_Visitor;

use Symfony\Bridge\Twig\Node\Trans_Node;
use Twig\Environment;
use Twig\Node\Expression\Binary\Concat_Binary;
use Twig\Node\Expression\Constant_Expression;
use Twig\Node\Expression\Filter_Expression;
use Twig\Node\Expression\Function_Expression;
use Twig\Node\Node;
use Twig\Node_Visitor\Node_Visitor_Interface;
/**
 * TranslationNodeVisitor extracts translation messages.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Translation_Node_Visitor implements Node_Visitor_Interface
{
    public const UNDEFINED_DOMAIN = '_undefined';
    private bool $enabled = false;
    private array $messages = [];
    public function enable(): void
    {
        $this->enabled = true;
        $this->messages = [];
    }
    public function disable(): void
    {
        $this->enabled = false;
        $this->messages = [];
    }
    public function get_messages(): array
    {
        return $this->messages;
    }
    public function enter_node(Node $node, Environment $env): Node
    {
        if (!$this->enabled) {
            return $node;
        }
        if ($node instanceof Filter_Expression && 'trans' === ($node->has_attribute('twig_callable') ? $node->get_attribute('twig_callable')->get_name() : $node->get_node('filter')->get_attribute('value')) && $node->get_node('node') instanceof Constant_Expression) {
            // extract constant nodes with a trans filter
            $this->messages[] = [$node->get_node('node')->get_attribute('value'), $this->get_read_domain_from_arguments($node->get_node('arguments'), 1)];
        } elseif ($node instanceof Function_Expression && 't' === $node->get_attribute('name')) {
            $node_arguments = $node->get_node('arguments');
            if ($node_arguments->getIterator()->current() instanceof Constant_Expression) {
                $this->messages[] = [$this->get_read_message_from_arguments($node_arguments, 0), $this->get_read_domain_from_arguments($node_arguments, 2)];
            }
        } elseif ($node instanceof Trans_Node) {
            // extract trans nodes
            $this->messages[] = [$node->get_node('body')->get_attribute('data'), $node->has_node('domain') ? $this->get_read_domain_from_node($node->get_node('domain')) : null];
        } elseif ($node instanceof Filter_Expression && 'trans' === ($node->has_attribute('twig_callable') ? $node->get_attribute('twig_callable')->get_name() : $node->get_node('filter')->get_attribute('value')) && $node->get_node('node') instanceof Concat_Binary && $message = $this->get_concat_value_from_node($node->get_node('node'), null)) {
            $this->messages[] = [$message, $this->get_read_domain_from_arguments($node->get_node('arguments'), 1)];
        }
        return $node;
    }
    public function leave_node(Node $node, Environment $env): \Twig\Node\Node
    {
        return $node;
    }
    public function get_priority(): int
    {
        return 0;
    }
    private function get_read_message_from_arguments(Node $arguments, int $index): ?string
    {
        if ($arguments->has_node('message')) {
            $argument = $arguments->get_node('message');
        } elseif ($arguments->has_node($index)) {
            $argument = $arguments->get_node($index);
        } else {
            return null;
        }
        return $this->get_read_message_from_node($argument);
    }
    private function get_read_message_from_node(Node $node): ?string
    {
        if ($node instanceof Constant_Expression) {
            return $node->get_attribute('value');
        }
        return null;
    }
    private function get_read_domain_from_arguments(Node $arguments, int $index): ?string
    {
        if ($arguments->has_node('domain')) {
            $argument = $arguments->get_node('domain');
        } elseif ($arguments->has_node($index)) {
            $argument = $arguments->get_node($index);
        } else {
            return null;
        }
        return $this->get_read_domain_from_node($argument);
    }
    private function get_read_domain_from_node(Node $node): ?string
    {
        if ($node instanceof Constant_Expression) {
            return $node->get_attribute('value');
        }
        if ($node instanceof Function_Expression && 'constant' === $node->get_attribute('name')) {
            $node_arguments = $node->get_node('arguments');
            if ($node_arguments->getIterator()->current() instanceof Constant_Expression) {
                $constant_name = $node_arguments->getIterator()->current()->get_attribute('value');
                if (\defined($constant_name)) {
                    $value = \constant($constant_name);
                    if (\is_string($value)) {
                        return $value;
                    }
                }
            }
        }
        return self::UNDEFINED_DOMAIN;
    }
    private function get_concat_value_from_node(Node $node, ?string $value): ?string
    {
        if ($node instanceof Concat_Binary) {
            foreach ($node as $next_node) {
                if ($next_node instanceof Concat_Binary) {
                    $next_value = $this->get_concat_value_from_node($next_node, $value);
                    if (null === $next_value) {
                        return null;
                    }
                    $value .= $next_value;
                } elseif ($next_node instanceof Constant_Expression) {
                    $value .= $next_node->get_attribute('value');
                } else {
                    // this is a node we cannot process (variable, or translation in translation)
                    return null;
                }
            }
        } elseif ($node instanceof Constant_Expression) {
            $value .= $node->get_attribute('value');
        }
        return $value;
    }
}
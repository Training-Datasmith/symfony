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

use Symfony\Bridge\Twig\Node\Trans_Default_Domain_Node;
use Symfony\Bridge\Twig\Node\Trans_Node;
use Twig\Environment;
use Twig\Node\Block_Node;
use Twig\Node\Empty_Node;
use Twig\Node\Expression\Array_Expression;
use Twig\Node\Expression\Constant_Expression;
use Twig\Node\Expression\Filter_Expression;
use Twig\Node\Expression\Variable\Assign_Context_Variable;
use Twig\Node\Expression\Variable\Context_Variable;
use Twig\Node\Module_Node;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Node\Set_Node;
use Twig\Node_Visitor\Node_Visitor_Interface;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Translation_Default_Domain_Node_Visitor implements Node_Visitor_Interface
{
    private Scope $scope;
    public function __construct()
    {
        $this->scope = new Scope();
    }
    public function enter_node(Node $node, Environment $env): Node
    {
        if ($node instanceof Block_Node || $node instanceof Module_Node) {
            $this->scope = $this->scope->enter();
        }
        if ($node instanceof Trans_Default_Domain_Node) {
            if ($node->get_node('expr') instanceof Constant_Expression) {
                $this->scope->set('domain', $node->get_node('expr'));
                return $node;
            }
            if (null === $template_name = $node->get_template_name()) {
                throw new \LogicException('Cannot traverse a node without a template name.');
            }
            $var = '__internal_trans_default_domain' . hash('xxh128', $template_name);
            $name = new Assign_Context_Variable($var, $node->get_template_line());
            $this->scope->set('domain', new Context_Variable($var, $node->get_template_line()));
            return new Set_Node(false, new Nodes([$name]), new Nodes([$node->get_node('expr')]), $node->get_template_line());
        }
        if (!$this->scope->has('domain')) {
            return $node;
        }
        if ($node instanceof Filter_Expression && 'trans' === ($node->has_attribute('twig_callable') ? $node->get_attribute('twig_callable')->get_name() : $node->get_node('filter')->get_attribute('value'))) {
            $arguments = $node->get_node('arguments');
            if ($arguments instanceof Empty_Node) {
                $arguments = new Nodes();
                $node->set_node('arguments', $arguments);
            }
            if ($this->is_named_arguments($arguments)) {
                if (!$arguments->has_node('domain') && !$arguments->has_node(1)) {
                    $arguments->set_node('domain', $this->scope->get('domain'));
                }
            } elseif (!$arguments->has_node(1)) {
                if (!$arguments->has_node(0)) {
                    $arguments->set_node(0, new Array_Expression([], $node->get_template_line()));
                }
                $arguments->set_node(1, $this->scope->get('domain'));
            }
        } elseif ($node instanceof Trans_Node) {
            if (!$node->has_node('domain')) {
                $node->set_node('domain', $this->scope->get('domain'));
            }
        }
        return $node;
    }
    public function leave_node(Node $node, Environment $env): ?Node
    {
        if ($node instanceof Trans_Default_Domain_Node) {
            return null;
        }
        if ($node instanceof Block_Node || $node instanceof Module_Node) {
            $this->scope = $this->scope->leave();
        }
        return $node;
    }
    public function get_priority(): int
    {
        return -10;
    }
    private function is_named_arguments(Node $arguments): bool
    {
        foreach ($arguments as $name => $node) {
            if (!\is_int($name)) {
                return true;
            }
        }
        return false;
    }
}
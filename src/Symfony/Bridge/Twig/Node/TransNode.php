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
namespace Symfony\Bridge\Twig\Node;

use Twig\Attribute\Yield_Ready;
use Twig\Compiler;
use Twig\Node\Expression\Abstract_Expression;
use Twig\Node\Expression\Array_Expression;
use Twig\Node\Expression\Constant_Expression;
use Twig\Node\Expression\Variable\Context_Variable;
use Twig\Node\Node;
use Twig\Node\Text_Node;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[Yield_Ready]
final class Trans_Node extends Node
{
    public function __construct(Node $body, ?Node $domain = null, ?Abstract_Expression $count = null, ?Abstract_Expression $vars = null, ?Abstract_Expression $locale = null, int $lineno = 0)
    {
        $nodes = ['body' => $body];
        if (null !== $domain) {
            $nodes['domain'] = $domain;
        }
        if (null !== $count) {
            $nodes['count'] = $count;
        }
        if (null !== $vars) {
            $nodes['vars'] = $vars;
        }
        if (null !== $locale) {
            $nodes['locale'] = $locale;
        }
        parent::__construct($nodes, [], $lineno);
    }
    public function compile(Compiler $compiler): void
    {
        $compiler->add_debug_info($this);
        $defaults = new Array_Expression([], -1);
        if ($this->has_node('vars') && ($vars = $this->get_node('vars')) instanceof Array_Expression) {
            $defaults = $this->get_node('vars');
            $vars = null;
        }
        [$msg, $defaults] = $this->compile_string($this->get_node('body'), $defaults, (bool) $vars);
        $compiler->write('yield $this->env->getExtension(\'Symfony\Bridge\Twig\Extension\TranslationExtension\')->trans(')->subcompile($msg);
        $compiler->raw(', ');
        if (null !== $vars) {
            $compiler->raw('array_merge(')->subcompile($defaults)->raw(', ')->subcompile($this->get_node('vars'))->raw(')');
        } else {
            $compiler->subcompile($defaults);
        }
        $compiler->raw(', ');
        if (!$this->has_node('domain')) {
            $compiler->repr('messages');
        } else {
            $compiler->subcompile($this->get_node('domain'));
        }
        if ($this->has_node('locale')) {
            $compiler->raw(', ')->subcompile($this->get_node('locale'));
        } elseif ($this->has_node('count')) {
            $compiler->raw(', null');
        }
        if ($this->has_node('count')) {
            $compiler->raw(', ')->subcompile($this->get_node('count'));
        }
        $compiler->raw(");\n");
    }
    private function compile_string(Node $body, Array_Expression $vars, bool $ignore_strict_check = false): array
    {
        if ($body instanceof Constant_Expression) {
            $msg = $body->get_attribute('value');
        } elseif ($body instanceof Text_Node) {
            $msg = $body->get_attribute('data');
        } else {
            return [$body, $vars];
        }
        preg_match_all('/(?<!%)%([^%]+)%/', $msg, $matches);
        foreach ($matches[1] as $var) {
            $key = new Constant_Expression('%' . $var . '%', $body->get_template_line());
            if (!$vars->has_element($key)) {
                if ('count' === $var && $this->has_node('count')) {
                    $vars->add_element($this->get_node('count'), $key);
                } else {
                    $var_expr = new Context_Variable($var, $body->get_template_line());
                    $var_expr->set_attribute('ignore_strict_check', $ignore_strict_check);
                    $vars->add_element($var_expr, $key);
                }
            }
        }
        return [new Constant_Expression(str_replace('%%', '%', trim($msg)), $body->get_template_line()), $vars];
    }
}
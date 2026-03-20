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
use Twig\Node\Expression\Variable\Local_Variable;
use Twig\Node\Node;
/**
 * @author Julien Galenski <julien.galenski@gmail.com>
 */
#[Yield_Ready]
final class Dump_Node extends Node
{
    public function __construct(private readonly Local_Variable|string $var_prefix, ?Node $values, int $lineno)
    {
        $nodes = [];
        if (null !== $values) {
            $nodes['values'] = $values;
        }
        parent::__construct($nodes, [], $lineno);
    }
    public function compile(Compiler $compiler): void
    {
        if ($this->var_prefix instanceof Local_Variable) {
            $var_prefix = $this->var_prefix->get_attribute('name');
        } else {
            $var_prefix = $this->var_prefix;
        }
        $compiler->write("if (\$this->env->isDebug()) {\n")->indent();
        if (!$this->has_node('values')) {
            // remove embedded templates (macros) from the context
            $compiler->write(\sprintf('$%svars = [];' . "\n", $var_prefix))->write(\sprintf('foreach ($context as $%1$skey => $%1$sval) {' . "\n", $var_prefix))->indent()->write(\sprintf('if (!$%sval instanceof \Twig\Template) {' . "\n", $var_prefix))->indent()->write(\sprintf('$%1$svars[$%1$skey] = $%1$sval;' . "\n", $var_prefix))->outdent()->write("}\n")->outdent()->write("}\n")->add_debug_info($this)->write(\sprintf('\Symfony\Component\VarDumper\VarDumper::dump($%svars);' . "\n", $var_prefix));
        } elseif (($values = $this->get_node('values')) && 1 === $values->count()) {
            $compiler->add_debug_info($this)->write('\Symfony\Component\VarDumper\VarDumper::dump(')->subcompile($values->get_node(0))->raw(");\n");
        } else {
            $compiler->add_debug_info($this)->write('\Symfony\Component\VarDumper\VarDumper::dump([' . "\n")->indent();
            foreach ($values as $node) {
                $compiler->write('');
                if ($node->has_attribute('name')) {
                    $compiler->string($node->get_attribute('name'))->raw(' => ');
                }
                $compiler->subcompile($node)->raw(",\n");
            }
            $compiler->outdent()->write("]);\n");
        }
        $compiler->outdent()->write("}\n");
    }
}
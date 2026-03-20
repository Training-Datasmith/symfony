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
use Twig\Node\Expression\Assign_Name_Expression;
use Twig\Node\Expression\Variable\Local_Variable;
use Twig\Node\Node;
/**
 * Represents a stopwatch node.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
#[Yield_Ready]
final class Stopwatch_Node extends Node
{
    public function __construct(Node $name, Node $body, Assign_Name_Expression|Local_Variable $var, int $lineno = 0)
    {
        parent::__construct(['body' => $body, 'name' => $name, 'var' => $var], [], $lineno);
    }
    public function compile(Compiler $compiler): void
    {
        $compiler->add_debug_info($this)->write('')->subcompile($this->get_node('var'))->raw(' = ')->subcompile($this->get_node('name'))->write(";\n")->write("\$this->env->getExtension('Symfony\\Bridge\\Twig\\Extension\\StopwatchExtension')->getStopwatch()->start(")->subcompile($this->get_node('var'))->raw(", 'template');\n")->subcompile($this->get_node('body'))->write("\$this->env->getExtension('Symfony\\Bridge\\Twig\\Extension\\StopwatchExtension')->getStopwatch()->stop(")->subcompile($this->get_node('var'))->raw(");\n");
    }
}
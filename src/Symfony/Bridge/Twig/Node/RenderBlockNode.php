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

use Twig\Compiler;
use Twig\Node\Expression\Function_Expression;
/**
 * Compiles a call to {@link \Symfony\Component\Form\FormRendererInterface::renderBlock()}.
 *
 * The function name is used as block name. For example, if the function name
 * is "foo", the block "foo" will be rendered.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class Render_Block_Node extends Function_Expression
{
    public function compile(Compiler $compiler): void
    {
        $compiler->add_debug_info($this);
        $arguments = iterator_to_array($this->get_node('arguments'));
        $compiler->write('$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->renderBlock(');
        if (isset($arguments[0])) {
            $compiler->subcompile($arguments[0]);
            $compiler->raw(', \'' . $this->get_attribute('name') . '\'');
            if (isset($arguments[1])) {
                $compiler->raw(', ');
                $compiler->subcompile($arguments[1]);
            }
        }
        $compiler->raw(')');
    }
}
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

use Symfony\Component\Form\Form_Renderer;
use Twig\Attribute\Yield_Ready;
use Twig\Compiler;
use Twig\Node\Node;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[Yield_Ready]
final class Form_Theme_Node extends Node
{
    public function __construct(Node $form, Node $resources, int $lineno, bool $only = false)
    {
        parent::__construct(['form' => $form, 'resources' => $resources], ['only' => $only], $lineno);
    }
    public function compile(Compiler $compiler): void
    {
        $compiler->add_debug_info($this)->write('$this->env->getRuntime(')->string(Form_Renderer::class)->raw(')->setTheme(')->subcompile($this->get_node('form'))->raw(', ')->subcompile($this->get_node('resources'))->raw(', ')->raw(false === $this->get_attribute('only') ? 'true' : 'false')->raw(");\n");
    }
}
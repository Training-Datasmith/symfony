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
use Twig\Node\Expression\Array_Expression;
use Twig\Node\Expression\Constant_Expression;
use Twig\Node\Expression\Function_Expression;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class Search_And_Render_Block_Node extends Function_Expression
{
    public function compile(Compiler $compiler): void
    {
        $compiler->add_debug_info($this);
        $compiler->raw('$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(');
        preg_match('/_([^_]+)$/', $this->get_attribute('name'), $matches);
        $arguments = iterator_to_array($this->get_node('arguments'));
        $block_name_suffix = $matches[1];
        if (isset($arguments[0])) {
            $compiler->subcompile($arguments[0]);
            $compiler->raw(', \'' . $block_name_suffix . '\'');
            if (isset($arguments[1])) {
                if ('label' === $block_name_suffix) {
                    // The "label" function expects the label in the second and
                    // the variables in the third argument
                    $label = $arguments[1];
                    $variables = $arguments[2] ?? null;
                    $lineno = $label->get_template_line();
                    if ($label instanceof Constant_Expression) {
                        // If the label argument is given as a constant, we can either
                        // strip it away if it is empty, or integrate it into the array
                        // of variables at compile time.
                        $label_is_expression = false;
                        // Only insert the label into the array if it is not empty
                        if (null !== $label->get_attribute('value') && false !== $label->get_attribute('value') && '' !== (string) $label->get_attribute('value')) {
                            $original_variables = $variables;
                            $variables = new Array_Expression([], $lineno);
                            $label_key = new Constant_Expression('label', $lineno);
                            if (null !== $original_variables) {
                                foreach ($original_variables->get_key_value_pairs() as $pair) {
                                    // Don't copy the original label attribute over if it exists
                                    if ((string) $label_key !== (string) $pair['key']) {
                                        $variables->add_element($pair['value'], $pair['key']);
                                    }
                                }
                            }
                            // Insert the label argument into the array
                            $variables->add_element($label, $label_key);
                        }
                    } else {
                        // The label argument is not a constant, but some kind of
                        // expression. This expression needs to be evaluated at runtime.
                        // Depending on the result (whether it is null or not), the
                        // label in the arguments should take precedence over the label
                        // in the attributes or not.
                        $label_is_expression = true;
                    }
                } else {
                    // All other functions than "label" expect the variables
                    // in the second argument
                    $label = null;
                    $variables = $arguments[1];
                    $label_is_expression = false;
                }
                if (null !== $variables || $label_is_expression) {
                    $compiler->raw(', ');
                    if (null !== $variables) {
                        $compiler->subcompile($variables);
                    }
                    if ($label_is_expression) {
                        if (null !== $variables) {
                            $compiler->raw(' + ');
                        }
                        // Check at runtime whether the label is empty.
                        // If not, add it to the array at runtime.
                        $compiler->raw('(CoreExtension::testEmpty($_label_ = ');
                        $compiler->subcompile($label);
                        $compiler->raw(') ? [] : ["label" => $_label_])');
                    }
                }
            }
        }
        $compiler->raw(')');
    }
}
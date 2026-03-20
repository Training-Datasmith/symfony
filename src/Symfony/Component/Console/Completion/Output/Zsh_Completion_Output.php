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
namespace Symfony\Component\Console\Completion\Output;

use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @author Jitendra A <adhocore@gmail.com>
 */
class Zsh_Completion_Output implements Completion_Output_Interface
{
    public function write(Completion_Suggestions $suggestions, Output_Interface $output): void
    {
        $values = [];
        foreach ($suggestions->get_value_suggestions() as $value) {
            $values[] = $value->get_value() . ($value->get_description() ? "\t" . $value->get_description() : '');
        }
        foreach ($suggestions->get_option_suggestions() as $option) {
            $values[] = '--' . $option->get_name() . ($option->get_description() ? "\t" . $option->get_description() : '');
            if ($option->is_negatable()) {
                $values[] = '--no-' . $option->get_name() . ($option->get_description() ? "\t" . $option->get_description() : '');
            }
        }
        $output->write(implode("\n", $values) . "\n");
    }
}
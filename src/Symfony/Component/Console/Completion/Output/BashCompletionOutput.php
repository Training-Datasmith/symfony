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
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class Bash_Completion_Output implements Completion_Output_Interface
{
    public function write(Completion_Suggestions $suggestions, Output_Interface $output): void
    {
        $values = $suggestions->get_value_suggestions();
        foreach ($suggestions->get_option_suggestions() as $option) {
            $values[] = '--' . $option->get_name();
            if ($option->is_negatable()) {
                $values[] = '--no-' . $option->get_name();
            }
        }
        $output->writeln(implode("\n", $values));
    }
}
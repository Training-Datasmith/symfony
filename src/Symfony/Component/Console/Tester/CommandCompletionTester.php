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
namespace Symfony\Component\Console\Tester;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
/**
 * Eases the testing of command completion.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class Command_Completion_Tester
{
    public function __construct(private readonly Command $command)
    {
    }
    /**
     * Create completion suggestions from input tokens.
     */
    public function complete(array $input): array
    {
        $current_index = \count($input);
        if ('' === end($input)) {
            array_pop($input);
        }
        array_unshift($input, $this->command->get_name());
        $completion_input = Completion_Input::from_tokens($input, $current_index);
        $completion_input->bind($this->command->get_definition());
        $suggestions = new Completion_Suggestions();
        $this->command->complete($completion_input, $suggestions);
        $options = [];
        foreach ($suggestions->get_option_suggestions() as $option) {
            $options[] = '--' . $option->get_name();
        }
        return array_map(strval(...), array_merge($options, $suggestions->get_value_suggestions()));
    }
}
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
namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Question\Choice_Question;
use Symfony\Component\Console\Question\Confirmation_Question;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * Symfony Style Guide compliant question helper.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class Symfony_Question_Helper extends Question_Helper
{
    protected function write_prompt(Output_Interface $output, Question $question): void
    {
        $text = Output_Formatter::escape_trailing_backslash($question->get_question());
        $default = $question->get_default();
        if ($question->is_multiline()) {
            $text .= \sprintf(' (press %s to continue)', $this->get_eof_shortcut($output));
        }
        switch (true) {
            case null === $default:
                $text = \sprintf(' <info>%s</info>:', $text);
                break;
            case $question instanceof Confirmation_Question:
                $text = \sprintf(' <info>%s (yes/no)</info> [<comment>%s</comment>]:', $text, $default ? 'yes' : 'no');
                break;
            case $question instanceof Choice_Question && $question->is_multiselect():
                $choices = $question->get_choices();
                $default = explode(',', $default);
                foreach ($default as $key => $value) {
                    $default[$key] = $choices[trim($value)];
                }
                $text = \sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, Output_Formatter::escape(implode(', ', $default)));
                break;
            case $question instanceof Choice_Question:
                $choices = $question->get_choices();
                $text = \sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, Output_Formatter::escape($choices[$default] ?? $default));
                break;
            default:
                $text = \sprintf(' <info>%s</info> [<comment>%s</comment>]:', $text, Output_Formatter::escape($default));
        }
        $output->writeln($text);
        $prompt = ' > ';
        if ($question instanceof Choice_Question) {
            $output->writeln($this->format_choice_question_choices($question, 'comment'));
            $prompt = $question->get_prompt();
        }
        $output->write($prompt);
    }
    protected function write_error(Output_Interface $output, \Exception $error): void
    {
        if ($output instanceof Symfony_Style) {
            $output->new_line();
            $output->error($error->get_message());
            return;
        }
        parent::write_error($output, $error);
    }
    private function get_eof_shortcut(Output_Interface $output): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR && !$output->is_decorated()) {
            return '<comment>Ctrl+Z</comment> then <comment>Enter</comment>';
        }
        return '<comment>Ctrl+D</comment>';
    }
}
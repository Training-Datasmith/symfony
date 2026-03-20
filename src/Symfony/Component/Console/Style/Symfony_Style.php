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
namespace Symfony\Component\Console\Style;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\Output_Wrapper;
use Symfony\Component\Console\Helper\Progress_Bar;
use Symfony\Component\Console\Helper\Symfony_Question_Helper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\Table_Cell;
use Symfony\Component\Console\Helper\Table_Separator;
use Symfony\Component\Console\Helper\Tree_Helper;
use Symfony\Component\Console\Helper\Tree_Node;
use Symfony\Component\Console\Helper\Tree_Style;
use Symfony\Component\Console\Input\File\Input_File;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Console_Section_Output;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Output\Trimmed_Buffer_Output;
use Symfony\Component\Console\Question\Choice_Question;
use Symfony\Component\Console\Question\Confirmation_Question;
use Symfony\Component\Console\Question\File_Question;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Terminal;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
/**
 * Output decorator helpers for the Symfony Style Guide.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class Symfony_Style extends Output_Style
{
    public const MAX_LINE_LENGTH = 120;
    private Symfony_Question_Helper $question_helper;
    private Progress_Bar $progress_bar;
    private readonly int $line_length;
    private readonly Trimmed_Buffer_Output $buffered_output;
    public function __construct(private readonly Input_Interface $input, private readonly Output_Interface $output, private readonly ?Event_Dispatcher_Interface $dispatcher = null)
    {
        $this->buffered_output = new Trimmed_Buffer_Output(\DIRECTORY_SEPARATOR === '\\' ? 4 : 2, $output->get_verbosity(), false, clone $output->get_formatter());
        // Windows cmd wraps lines as soon as the terminal width is reached, whether there are following chars or not.
        $width = (new Terminal())->get_width() ?: self::MAX_LINE_LENGTH;
        $this->line_length = min($width - (int) (\DIRECTORY_SEPARATOR === '\\'), self::MAX_LINE_LENGTH);
        parent::__construct($output);
    }
    /**
     * Formats a message as a block of text.
     */
    public function block(string|array $messages, ?string $type = null, ?string $style = null, string $prefix = ' ', bool $padding = false, bool $escape = true): void
    {
        $messages = \is_array($messages) ? array_values($messages) : [$messages];
        $this->auto_prepend_block();
        $this->writeln($this->create_block($messages, $type, $style, $prefix, $padding, $escape));
        $this->new_line();
    }
    public function title(string $message): void
    {
        $this->auto_prepend_block();
        $this->writeln([\sprintf('<comment>%s</>', Output_Formatter::escape_trailing_backslash($message)), \sprintf('<comment>%s</>', str_repeat('=', Helper::width(Helper::remove_decoration($this->get_formatter(), $message))))]);
        $this->new_line();
    }
    public function section(string $message): void
    {
        $this->auto_prepend_block();
        $this->writeln([\sprintf('<comment>%s</>', Output_Formatter::escape_trailing_backslash($message)), \sprintf('<comment>%s</>', str_repeat('-', Helper::width(Helper::remove_decoration($this->get_formatter(), $message))))]);
        $this->new_line();
    }
    public function listing(array $elements): void
    {
        $this->auto_prepend_text();
        $elements = array_map(static fn(string $element): string => \sprintf(' * %s', $element), $elements);
        $this->writeln($elements);
        $this->new_line();
    }
    public function text(string|array $message): void
    {
        $this->auto_prepend_text();
        $messages = \is_array($message) ? array_values($message) : [$message];
        foreach ($messages as $message) {
            $this->writeln(\sprintf(' %s', $message));
        }
    }
    /**
     * Formats a command comment.
     */
    public function comment(string|array $message): void
    {
        $this->block($message, null, null, '<fg=default;bg=default> // </>', false, false);
    }
    public function success(string|array $message): void
    {
        $this->block($message, 'OK', 'fg=black;bg=green', ' ', true);
    }
    public function error(string|array $message): void
    {
        $this->block($message, 'ERROR', 'fg=white;bg=red', ' ', true);
    }
    public function warning(string|array $message): void
    {
        $this->block($message, 'WARNING', 'fg=black;bg=yellow', ' ', true);
    }
    public function note(string|array $message): void
    {
        $this->block($message, 'NOTE', 'fg=yellow', ' ! ');
    }
    /**
     * Formats an info message.
     */
    public function info(string|array $message): void
    {
        $this->block($message, 'INFO', 'fg=green', ' ', true);
    }
    public function caution(string|array $message): void
    {
        $this->block($message, 'CAUTION', 'fg=white;bg=red', ' ! ', true);
    }
    public function table(array $headers, array $rows): void
    {
        $this->create_table()->set_headers($headers)->set_rows($rows)->render();
        $this->new_line();
    }
    /**
     * Formats a horizontal table.
     */
    public function horizontal_table(array $headers, array $rows): void
    {
        $this->create_table()->set_horizontal(true)->set_headers($headers)->set_rows($rows)->render();
        $this->new_line();
    }
    /**
     * Formats a list of key/value horizontally.
     *
     * Each row can be one of:
     * * 'A title'
     * * ['key' => 'value']
     * * new TableSeparator()
     */
    public function definition_list(string|array|Table_Separator ...$list): void
    {
        $headers = [];
        $row = [];
        foreach ($list as $value) {
            if ($value instanceof Table_Separator) {
                $headers[] = $value;
                $row[] = $value;
                continue;
            }
            if (\is_string($value)) {
                $headers[] = new Table_Cell($value, ['colspan' => 2]);
                $row[] = null;
                continue;
            }
            if (!\is_array($value)) {
                throw new InvalidArgumentException('Value should be an array, string, or an instance of TableSeparator.');
            }
            $headers[] = key($value);
            $row[] = current($value);
        }
        $this->horizontal_table($headers, [$row]);
    }
    public function ask(string $question, ?string $default = null, ?callable $validator = null): mixed
    {
        $question = new Question($question, $default);
        $question->set_validator($validator);
        return $this->ask_question($question);
    }
    public function ask_hidden(string $question, ?callable $validator = null): mixed
    {
        $question = new Question($question);
        $question->set_hidden(true);
        $question->set_validator($validator);
        return $this->ask_question($question);
    }
    public function confirm(string $question, bool $default = true): bool
    {
        return $this->ask_question(new Confirmation_Question($question, $default));
    }
    public function choice(string $question, array $choices, mixed $default = null, bool $multi_select = false): mixed
    {
        if (null !== $default) {
            $values = array_flip($choices);
            $default = $values[$default] ?? $default;
        }
        $question_choice = new Choice_Question($question, $choices, $default);
        $question_choice->set_multiselect($multi_select);
        return $this->ask_question($question_choice);
    }
    public function ask_file(string $question): ?Input_File
    {
        return $this->ask_question(new File_Question($question));
    }
    /**
     * @param string|null $format
     */
    public function progress_start(int $max = 0): void
    {
        $this->progress_bar = $this->create_progress_bar($max);
        $this->progress_bar->start();
    }
    public function progress_advance(int $step = 1): void
    {
        $this->get_progress_bar()->advance($step);
    }
    public function progress_finish(): void
    {
        $this->get_progress_bar()->finish();
        $this->new_line(2);
        unset($this->progress_bar);
    }
    /**
     * @param string|null $format
     */
    public function create_progress_bar(int $max = 0): Progress_Bar
    {
        $format = 2 <= \func_num_args() ? func_get_arg(1) : null;
        $progress_bar = parent::create_progress_bar($max);
        if ('\\' !== \DIRECTORY_SEPARATOR || 'Hyper' === getenv('TERM_PROGRAM')) {
            $progress_bar->set_empty_bar_character('░');
            // light shade character \u2591
            $progress_bar->set_progress_character('');
            $progress_bar->set_bar_character('▓');
            // dark shade character \u2593
        }
        if (null !== $format) {
            $progress_bar->set_format($format);
        }
        return $progress_bar;
    }
    /**
     * @see ProgressBar::iterate()
     *
     * @template TKey
     * @template TValue
     *
     * @param iterable<TKey, TValue> $iterable
     * @param int|null               $max      Number of steps to complete the bar (0 if indeterminate), if null it will be inferred from $iterable
     * @param string|null            $format   A ProgressBar format string (e.g. ' %current%/%max% [%bar%] %memory:6s%'); null uses the default format
     *
     * @return iterable<TKey, TValue>
     */
    public function progress_iterate(iterable $iterable, ?int $max = null): iterable
    {
        yield from $this->create_progress_bar(0)->iterate($iterable, $max);
        $this->new_line(2);
    }
    public function ask_question(Question $question): mixed
    {
        if ($this->input->is_interactive()) {
            $this->auto_prepend_block();
        }
        $this->question_helper ??= new Symfony_Question_Helper($this->dispatcher);
        $answer = $this->question_helper->ask($this->input, $this, $question);
        if ($this->input->is_interactive()) {
            if ($this->output instanceof Console_Section_Output) {
                // add the new line of the `return` to submit the input to ConsoleSectionOutput, because ConsoleSectionOutput is holding all it's lines.
                // this is relevant when a `ConsoleSectionOutput::clear` is called.
                $this->output->add_new_line_of_input_submit();
            }
            $this->new_line();
            $this->buffered_output->write("\n");
        }
        return $answer;
    }
    public function writeln(string|iterable $messages, int $type = self::OUTPUT_NORMAL): void
    {
        if (!is_iterable($messages)) {
            $messages = [$messages];
        }
        foreach ($messages as $message) {
            parent::writeln($message, $type);
            $this->write_buffer($message, true, $type);
        }
    }
    public function write(string|iterable $messages, bool $newline = false, int $type = self::OUTPUT_NORMAL): void
    {
        if (!is_iterable($messages)) {
            $messages = [$messages];
        }
        foreach ($messages as $message) {
            parent::write($message, $newline, $type);
            $this->write_buffer($message, $newline, $type);
        }
    }
    public function new_line(int $count = 1): void
    {
        parent::new_line($count);
        $this->buffered_output->write(str_repeat("\n", $count));
    }
    /**
     * Returns a new instance which makes use of stderr if available.
     */
    public function get_error_style(): self
    {
        return new self($this->input, $this->get_error_output());
    }
    public function create_table(): Table
    {
        $output = $this->output instanceof Console_Output_Interface ? $this->output->section() : $this->output;
        $style = clone Table::get_style_definition('symfony-style-guide');
        $style->set_cell_header_format('<info>%s</info>');
        return (new Table($output))->set_style($style);
    }
    private function get_progress_bar(): Progress_Bar
    {
        return $this->progress_bar ?? throw new RuntimeException('The ProgressBar is not started.');
    }
    /**
     * @param iterable<string, iterable|string|TreeNode> $nodes
     */
    public function tree(iterable $nodes, string $root = ''): void
    {
        $this->create_tree($nodes, $root)->render();
    }
    /**
     * @param iterable<string, iterable|string|TreeNode> $nodes
     */
    public function create_tree(iterable $nodes, string $root = ''): Tree_Helper
    {
        $output = $this->output instanceof Console_Output_Interface ? $this->output->section() : $this->output;
        return Tree_Helper::create_tree($output, $root, $nodes, Tree_Style::default());
    }
    private function auto_prepend_block(): void
    {
        $chars = substr(str_replace(\PHP_EOL, "\n", $this->buffered_output->fetch()), -2);
        if (!isset($chars[0])) {
            $this->new_line();
            // empty history, so we should start with a new line.
            return;
        }
        // Prepend new line for each non LF chars (This means no blank line was output before)
        $this->new_line(2 - substr_count($chars, "\n"));
    }
    private function auto_prepend_text(): void
    {
        $fetched = $this->buffered_output->fetch();
        // Prepend new line if last char isn't EOL:
        if ($fetched && !str_ends_with($fetched, "\n")) {
            $this->new_line();
        }
    }
    private function write_buffer(string $message, bool $new_line, int $type): void
    {
        // We need to know if the last chars are PHP_EOL
        $this->buffered_output->write($message, $new_line, $type);
    }
    private function create_block(iterable $messages, ?string $type = null, ?string $style = null, string $prefix = ' ', bool $padding = false, bool $escape = false): array
    {
        $indent_length = 0;
        $prefix_length = Helper::width(Helper::remove_decoration($this->get_formatter(), $prefix));
        $lines = [];
        if (null !== $type) {
            $type = \sprintf('[%s] ', $type);
            $indent_length = Helper::width($type);
            $line_indentation = str_repeat(' ', $indent_length);
        }
        // wrap and add newlines for each element
        $output_wrapper = new Output_Wrapper();
        foreach ($messages as $key => $message) {
            if ($escape) {
                $message = Output_Formatter::escape($message);
            }
            $message = str_replace("\r\n", "\n", $message);
            $lines = array_merge($lines, explode("\n", $output_wrapper->wrap($message, $this->line_length - $prefix_length - $indent_length, "\n")));
            if (\count($messages) > 1 && $key < \count($messages) - 1) {
                $lines[] = '';
            }
        }
        $first_line_index = 0;
        if ($padding && $this->is_decorated()) {
            $first_line_index = 1;
            array_unshift($lines, '');
            $lines[] = '';
        }
        foreach ($lines as $i => &$line) {
            if (null !== $type) {
                $line = $first_line_index === $i ? $type . $line : $line_indentation . $line;
            }
            $line = $prefix . $line;
            $line .= str_repeat(' ', max($this->line_length - Helper::width(Helper::remove_decoration($this->get_formatter(), $line)), 0));
            if ($style) {
                $line = \sprintf('<%s>%s</>', $style, $line);
            }
        }
        return $lines;
    }
}
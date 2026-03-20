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

use Symfony\Component\Console\Console_Events;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Event\Question_Answered_Event;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\Missing_Input_Exception;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Formatter\Output_Formatter_Style;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Streamable_Input_Interface;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Console_Section_Output;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Question\Choice_Question;
use Symfony\Component\Console\Question\File_Question;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Terminal;
use function Symfony\Component\String\s;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
/**
 * The QuestionHelper class provides helpers to interact with the user.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Question_Helper extends Helper
{
    private static bool $stty = true;
    private static bool $stdin_is_interactive;
    public function __construct(private readonly ?Event_Dispatcher_Interface $dispatcher = null)
    {
    }
    /**
     * Asks a question to the user.
     *
     * @return mixed The user answer
     *
     * @throws RuntimeException If there is no data to read in the input stream
     */
    public function ask(Input_Interface $input, Output_Interface $output, Question $question): mixed
    {
        if ($output instanceof Console_Output_Interface) {
            $output = $output->get_error_output();
        }
        if (!$input->is_interactive()) {
            return $this->get_default_answer($question);
        }
        $input_stream = $input instanceof Streamable_Input_Interface ? $input->get_stream() : null;
        $input_stream ??= \STDIN;
        try {
            if (!$question->get_validator() && !$question->get_constraints()) {
                return $this->do_ask($input_stream, $output, $question);
            }
            $interviewer = fn(): mixed => $this->do_ask($input_stream, $output, $question);
            return $this->validate_attempts($interviewer, $output, $question);
        } catch (Missing_Input_Exception $exception) {
            $input->set_interactive(false);
            if (null === $fallback_output = $this->get_default_answer($question)) {
                throw $exception;
            }
            return $fallback_output;
        }
    }
    public function get_name(): string
    {
        return 'question';
    }
    /**
     * Prevents usage of stty.
     */
    public static function disable_stty(): void
    {
        self::$stty = false;
    }
    /**
     * Asks the question to the user.
     *
     * @param resource $inputStream
     *
     * @throws RuntimeException In case the fallback is deactivated and the response cannot be hidden
     */
    private function do_ask($input_stream, Output_Interface $output, Question $question): mixed
    {
        if ($question instanceof File_Question) {
            $this->write_prompt($output, $question);
            return (new File_Input_Helper())->read_file_input($input_stream, $output, $question);
        }
        $this->write_prompt($output, $question);
        $autocomplete = $question->get_autocompleter_callback();
        if (null === $autocomplete || !self::$stty || !Terminal::has_stty_available()) {
            $ret = false;
            if ($question->is_hidden()) {
                try {
                    $hidden_response = $this->get_hidden_response($output, $input_stream, $question->is_trimmable());
                    $ret = $question->is_trimmable() ? trim($hidden_response) : $hidden_response;
                } catch (RuntimeException $e) {
                    if (!$question->is_hidden_fallback()) {
                        throw $e;
                    }
                }
            }
            if (false === $ret) {
                $is_blocked = stream_get_meta_data($input_stream)['blocked'] ?? true;
                if (!$is_blocked) {
                    stream_set_blocking($input_stream, true);
                }
                $ret = $this->read_input($input_stream, $question);
                if (!$is_blocked) {
                    stream_set_blocking($input_stream, false);
                }
                if (false === $ret) {
                    throw new Missing_Input_Exception('Aborted.');
                }
                if ($question->is_trimmable()) {
                    $ret = trim($ret);
                }
            }
        } else {
            $autocomplete = $this->autocomplete($output, $question, $input_stream, $autocomplete);
            $ret = $question->is_trimmable() ? trim($autocomplete) : $autocomplete;
        }
        if ($output instanceof Console_Section_Output) {
            $output->add_content('');
            // add EOL to the question
            $output->add_content($ret);
        }
        $ret = \strlen($ret) > 0 ? $ret : $question->get_default();
        if ($normalizer = $question->get_normalizer()) {
            return $normalizer($ret);
        }
        return $ret;
    }
    private function get_default_answer(Question $question): mixed
    {
        $default = $question->get_default();
        if (null === $default) {
            return $default;
        }
        if ($validator = $question->get_validator()) {
            return \call_user_func($validator, $default);
        }
        if ($question instanceof Choice_Question) {
            $choices = $question->get_choices();
            if (!$question->is_multiselect()) {
                return $choices[$default] ?? $default;
            }
            $default = explode(',', $default);
            foreach ($default as $k => $v) {
                $v = $question->is_trimmable() ? trim($v) : $v;
                $default[$k] = $choices[$v] ?? $v;
            }
        }
        return $default;
    }
    /**
     * Outputs the question prompt.
     */
    protected function write_prompt(Output_Interface $output, Question $question): void
    {
        $message = $question->get_question();
        if ($question instanceof Choice_Question) {
            $output->writeln(array_merge([$question->get_question()], $this->format_choice_question_choices($question, 'info')));
            $message = $question->get_prompt();
        }
        $output->write($message);
    }
    /**
     * @return string[]
     */
    protected function format_choice_question_choices(Choice_Question $question, string $tag): array
    {
        $messages = [];
        $max_width = max(array_map(self::width(...), array_keys($choices = $question->get_choices())));
        foreach ($choices as $key => $value) {
            $padding = str_repeat(' ', $max_width - self::width($key));
            $messages[] = \sprintf("  [<{$tag}>%s{$padding}</{$tag}>] %s", $key, $value);
        }
        return $messages;
    }
    /**
     * Outputs an error message.
     */
    protected function write_error(Output_Interface $output, \Exception $error): void
    {
        if (null !== $this->get_helper_set() && $this->get_helper_set()->has('formatter')) {
            $message = $this->get_helper_set()->get('formatter')->format_block($error->get_message(), 'error');
        } else {
            $message = '<error>' . $error->get_message() . '</error>';
        }
        $output->writeln($message);
    }
    /**
     * Autocompletes a question.
     *
     * @param resource                  $inputStream
     * @param callable(string):string[] $autocomplete
     */
    private function autocomplete(Output_Interface $output, Question $question, $input_stream, callable $autocomplete): string
    {
        $cursor = new Cursor($output, $input_stream);
        $full_choice = '';
        $ret = '';
        $i = 0;
        $ofs = -1;
        $matches = $autocomplete($ret);
        $num_matches = \count($matches);
        $input_helper = new Terminal_Input_Helper($input_stream);
        // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
        shell_exec('stty -icanon -echo');
        // Add highlighted text style
        $output->get_formatter()->set_style('hl', new Output_Formatter_Style('black', 'white'));
        // Read a keypress
        while (!feof($input_stream)) {
            $input_helper->wait_for_input();
            $c = fread($input_stream, 1);
            // as opposed to fgets(), fread() returns an empty string when the stream content is empty, not false.
            if (false === $c || '' === $ret && '' === $c && null === $question->get_default()) {
                // Restore the terminal so it behaves normally again
                $input_helper->finish();
                throw new Missing_Input_Exception('Aborted while asking: ' . $question->get_question());
            }
            // as opposed to fgets(), fread() returns an empty string when the stream content is empty, not false.
            if ("" === $c) {
                // Backspace Character
                if (0 === $num_matches && 0 !== $i) {
                    --$i;
                    $cursor->move_left(s($full_choice)->slice(-1)->width(false));
                    $full_choice = self::substr($full_choice, 0, $i);
                }
                if (0 === $i) {
                    $ofs = -1;
                    $matches = $autocomplete($ret);
                    $num_matches = \count($matches);
                } else {
                    $num_matches = 0;
                }
                // Pop the last character off the end of our string
                $ret = self::substr($ret, 0, $i);
            } elseif ("\x1b" === $c) {
                // Did we read an escape sequence?
                $c .= fread($input_stream, 2);
                // A = Up Arrow. B = Down Arrow
                if (isset($c[2]) && ('A' === $c[2] || 'B' === $c[2])) {
                    if ('A' === $c[2] && -1 === $ofs) {
                        $ofs = 0;
                    }
                    if (0 === $num_matches) {
                        continue;
                    }
                    $ofs += 'A' === $c[2] ? -1 : 1;
                    $ofs = ($num_matches + $ofs) % $num_matches;
                }
            } elseif ('' === $c || \ord($c) < 32) {
                if ("\t" === $c || "\n" === $c) {
                    if ($num_matches > 0 && -1 !== $ofs) {
                        $ret = (string) $matches[$ofs];
                        // Echo out remaining chars for current match
                        $remaining_characters = substr($ret, \strlen($this->most_recently_entered_value($full_choice)));
                        $output->write($remaining_characters);
                        $full_choice .= $remaining_characters;
                        $i = false === ($encoding = mb_detect_encoding($full_choice, null, true)) ? \strlen($full_choice) : mb_strlen($full_choice, $encoding);
                        $matches = array_filter($autocomplete($ret), static fn(string $match): bool => '' === $ret || str_starts_with($match, $ret));
                        $num_matches = \count($matches);
                        $ofs = -1;
                    }
                    if ("\n" === $c) {
                        $output->write($c);
                        break;
                    }
                    $num_matches = 0;
                }
                continue;
            } else {
                if ("\x80" <= $c) {
                    $c .= fread($input_stream, ["\xc0" => 1, "\xd0" => 1, "\xe0" => 2, "\xf0" => 3][$c & "\xf0"]);
                }
                $output->write($c);
                $ret .= $c;
                $full_choice .= $c;
                ++$i;
                $temp_ret = $ret;
                if ($question instanceof Choice_Question && $question->is_multiselect()) {
                    $temp_ret = $this->most_recently_entered_value($full_choice);
                }
                $num_matches = 0;
                $ofs = 0;
                foreach ($autocomplete($ret) as $value) {
                    // If typed characters match the beginning chunk of value (e.g. [AcmeDe]moBundle)
                    if (str_starts_with($value, $temp_ret)) {
                        $matches[$num_matches++] = $value;
                    }
                }
            }
            $cursor->clear_line_after();
            if ($num_matches > 0 && -1 !== $ofs) {
                $cursor->save_position();
                // Write highlighted text, complete the partially entered response
                $characters_entered = \strlen($this->most_recently_entered_value($full_choice));
                $output->write('<hl>' . Output_Formatter::escape_trailing_backslash(substr($matches[$ofs], $characters_entered)) . '</hl>');
                $cursor->restore_position();
            }
        }
        // Restore the terminal so it behaves normally again
        $input_helper->finish();
        return $full_choice;
    }
    private function most_recently_entered_value(string $entered): string
    {
        // Determine the most recent value that the user entered
        if (!str_contains($entered, ',')) {
            return $entered;
        }
        if (false === $last_comma_pos = strrpos($entered, ',')) {
            return $entered;
        }
        $last_choice = trim(substr($entered, $last_comma_pos + 1));
        return '' !== $last_choice ? $last_choice : $entered;
    }
    /**
     * Gets a hidden response from user.
     *
     * @param resource $inputStream The handler resource
     * @param bool     $trimmable   Is the answer trimmable
     *
     * @throws RuntimeException In case the fallback is deactivated and the response cannot be hidden
     */
    private function get_hidden_response(Output_Interface $output, $input_stream, bool $trimmable = true): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $exe = __DIR__ . '/../Resources/bin/hiddeninput.exe';
            // handle code running from a phar
            if (str_starts_with(__FILE__, 'phar:')) {
                $tmp_exe = sys_get_temp_dir() . '/hiddeninput.exe';
                copy($exe, $tmp_exe);
                $exe = $tmp_exe;
            }
            $s_exec = shell_exec('"' . $exe . '"');
            $value = $trimmable ? rtrim($s_exec) : $s_exec;
            $output->writeln('');
            if (isset($tmp_exe)) {
                unlink($tmp_exe);
            }
            return $value;
        }
        $input_helper = null;
        if (self::$stty && Terminal::has_stty_available()) {
            $input_helper = new Terminal_Input_Helper($input_stream);
            shell_exec('stty -echo');
        } elseif ($this->is_interactive_input($input_stream)) {
            throw new RuntimeException('Unable to hide the response.');
        }
        $value = $this->do_read_input($input_stream, helper: $input_helper);
        if (4095 === \strlen($value)) {
            $err_output = $output instanceof Console_Output_Interface ? $output->get_error_output() : $output;
            $err_output->warning('The value was possibly truncated by your shell or terminal emulator');
        }
        // Restore the terminal so it behaves normally again
        $input_helper?->finish();
        if ($trimmable) {
            $value = trim($value);
        }
        $output->writeln('');
        return $value;
    }
    /**
     * Validates an attempt.
     *
     * @param callable $interviewer A callable that will ask for a question and return the result
     *
     * @throws \Exception In case the max number of attempts has been reached and no valid response has been given
     */
    private function validate_attempts(callable $interviewer, Output_Interface $output, Question $question): mixed
    {
        $error = null;
        $attempts = $question->get_max_attempts();
        while (null === $attempts || $attempts--) {
            if (null !== $error) {
                $this->write_error($output, $error);
            }
            try {
                $value = $interviewer();
                if ($constraints = $question->get_constraints()) {
                    $this->validate_constraints($value, $constraints);
                }
                if ($validator = $question->get_validator()) {
                    return $validator($value);
                }
                return $value;
            } catch (Missing_Input_Exception $e) {
                throw $error ?? $e;
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Exception) {
            }
        }
        throw $error;
    }
    private function validate_constraints(mixed $value, array $constraints): void
    {
        if ($this->dispatcher) {
            $event = new Question_Answered_Event($value, $constraints);
            $this->dispatcher->dispatch($event, Console_Events::QUESTION_ANSWERED);
            if ($event->has_violations()) {
                throw new InvalidArgumentException($event->get_violations()[0]);
            }
            return;
        }
        $validator = Validation::create_validator();
        $violations = $validator->validate($value, $constraints);
        if (\count($violations) > 0) {
            throw new InvalidArgumentException($violations[0]->get_message());
        }
    }
    private function is_interactive_input($input_stream): bool
    {
        if ('php://stdin' !== (stream_get_meta_data($input_stream)['uri'] ?? null)) {
            return false;
        }
        return self::$stdin_is_interactive ?? self::$stdin_is_interactive = @stream_isatty(fopen('php://stdin', 'r'));
    }
    /**
     * Reads one or more lines of input and returns what is read.
     *
     * @param resource $inputStream The handler resource
     * @param Question $question    The question being asked
     */
    private function read_input($input_stream, Question $question): string|false
    {
        if (null !== $question->get_timeout() && $this->is_interactive_input($input_stream)) {
            $read = [$input_stream];
            $write = null;
            $except = null;
            $timeout_seconds = $question->get_timeout();
            $changed_streams = stream_select($read, $write, $except, $timeout_seconds);
            if (0 === $changed_streams) {
                throw new Missing_Input_Exception(\sprintf('Timed out after waiting for input for %d second%s.', $timeout_seconds, 1 === $timeout_seconds ? '' : 's'));
            }
        }
        if (!$question->is_multiline()) {
            $cp = $this->set_io_codepage();
            $ret = $this->do_read_input($input_stream);
            return $this->reset_io_codepage($cp, $ret);
        }
        $multi_line_stream_reader = $this->clone_input_stream($input_stream);
        if (null === $multi_line_stream_reader) {
            return false;
        }
        $cp = $this->set_io_codepage();
        $ret = $this->do_read_input($multi_line_stream_reader, "\x04");
        if (stream_get_meta_data($input_stream)['seekable']) {
            fseek($input_stream, ftell($multi_line_stream_reader));
        }
        return $this->reset_io_codepage($cp, $ret);
    }
    private function set_io_codepage(): int
    {
        if (\function_exists('sapi_windows_cp_set')) {
            $cp = sapi_windows_cp_get();
            sapi_windows_cp_set(sapi_windows_cp_get('oem'));
            return $cp;
        }
        return 0;
    }
    /**
     * Sets console I/O to the specified code page and converts the user input.
     */
    private function reset_io_codepage(int $cp, string|false $input): string|false
    {
        if (0 !== $cp) {
            sapi_windows_cp_set($cp);
            if (false !== $input && '' !== $input) {
                $input = sapi_windows_cp_conv(sapi_windows_cp_get('oem'), $cp, $input);
            }
        }
        return $input;
    }
    /**
     * Clones an input stream in order to act on one instance of the same
     * stream without affecting the other instance.
     *
     * @param resource $inputStream The handler resource
     *
     * @return resource|null The cloned resource, null in case it could not be cloned
     */
    private function clone_input_stream($input_stream)
    {
        $stream_meta_data = stream_get_meta_data($input_stream);
        $seekable = $stream_meta_data['seekable'] ?? false;
        $mode = $stream_meta_data['mode'] ?? 'rb';
        $uri = $stream_meta_data['uri'] ?? null;
        if (null === $uri) {
            return null;
        }
        $clone_stream = fopen($uri, $mode);
        // For seekable and writable streams, add all the same data to the
        // cloned stream and then seek to the same offset.
        if (true === $seekable && !\in_array($mode, ['r', 'rb', 'rt'], true)) {
            $offset = ftell($input_stream);
            rewind($input_stream);
            stream_copy_to_stream($input_stream, $clone_stream);
            fseek($input_stream, $offset);
            fseek($clone_stream, $offset);
        }
        return $clone_stream;
    }
    /**
     * @param resource $inputStream
     */
    private function do_read_input($input_stream, ?string $exit_char = null, ?Terminal_Input_Helper $helper = null): string
    {
        $ret = '';
        $helper ??= new Terminal_Input_Helper($input_stream, false);
        while (!feof($input_stream)) {
            $helper->wait_for_input();
            $char = fread($input_stream, 1);
            // as opposed to fgets(), fread() returns an empty string when the stream content is empty, not false.
            if (false === $char || '' === $ret && '' === $char) {
                throw new Missing_Input_Exception('Aborted.');
            }
            if (\PHP_EOL === "{$ret}{$char}" || $exit_char === $char) {
                break;
            }
            $ret .= $char;
            if (null === $exit_char && "\n" === $char) {
                break;
            }
        }
        return $ret;
    }
}
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
namespace Symfony\Bridge\Twig\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\CI\Github_Action_Reporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\Array_Loader;
use Twig\Loader\Filesystem_Loader;
use Twig\Source;
/**
 * Command that will validate your template syntax and output encountered errors.
 *
 * @author Marc Weistroff <marc.weistroff@sensiolabs.com>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
#[As_Command(name: 'lint:twig', description: 'Lint a Twig template and outputs encountered errors')]
class Lint_Command extends Command
{
    private array $excludes;
    private string $format;
    public function __construct(private readonly Environment $twig, private readonly array $name_patterns = ['*.twig'])
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_option('format', null, Input_Option::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', $this->get_available_format_options())))->add_option('show-deprecations', null, Input_Option::VALUE_NONE, 'Show deprecations as errors')->add_argument('filename', Input_Argument::IS_ARRAY, 'A file, a directory or "-" for reading from STDIN')->add_option('excludes', null, Input_Option::VALUE_REQUIRED | Input_Option::VALUE_IS_ARRAY, 'Excluded directories', [])->set_help(<<<'EOF'
        The <info>%command.name%</info> command lints a template and outputs to STDOUT
        the first encountered syntax error.
        
        You can validate the syntax of contents passed from STDIN:
        
          <info>cat filename | php %command.full_name% -</info>
        
        Or the syntax of a file:
        
          <info>php %command.full_name% filename</info>
        
        Or of a whole directory:
        
          <info>php %command.full_name% dirname</info>
        
        The <info>--format</info> option specifies the format of the command output:
        
          <info>php %command.full_name% dirname --format=json</info>
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $filenames = $input->get_argument('filename');
        $show_deprecations = $input->get_option('show-deprecations');
        $this->excludes = $input->get_option('excludes');
        $this->format = $input->get_option('format') ?? (Github_Action_Reporter::is_github_action_environment() ? 'github' : 'txt');
        if (['-'] === $filenames) {
            return $this->display($output, $io, [$this->validate(file_get_contents('php://stdin'), 'Standard Input', $show_deprecations)]);
        }
        if (!$filenames) {
            $loader = $this->twig->get_loader();
            if ($loader instanceof Filesystem_Loader) {
                $paths = [];
                foreach ($loader->get_namespaces() as $namespace) {
                    $paths[] = $loader->get_paths($namespace);
                }
                $filenames = array_merge(...$paths);
            }
            if (!$filenames) {
                throw new RuntimeException('Please provide a filename or pipe template content to STDIN.');
            }
        }
        return $this->display($output, $io, $this->get_files_info($filenames, $show_deprecations));
    }
    private function get_files_info(array $filenames, bool $show_deprecations): array
    {
        $files_info = [];
        foreach ($filenames as $filename) {
            foreach ($this->find_files($filename) as $file) {
                $files_info[] = $this->validate(file_get_contents($file), $file, $show_deprecations);
            }
        }
        return $files_info;
    }
    protected function find_files(string $filename): iterable
    {
        if (is_file($filename)) {
            return [$filename];
        }
        if (is_dir($filename)) {
            return Finder::create()->files()->in($filename)->name($this->name_patterns)->exclude($this->excludes);
        }
        throw new RuntimeException(\sprintf('File or directory "%s" is not readable.', $filename));
    }
    private function validate(string $template, string $file, bool $collect_deprecation): array
    {
        $deprecations = [];
        if ($collect_deprecation) {
            $prev_error_handler = set_error_handler(static function ($level, $message, $file_name, $line) use (&$prev_error_handler, &$deprecations, $file) {
                if (\E_USER_DEPRECATED === $level) {
                    $template_line = 0;
                    if (preg_match('/ at line (\d+)[ .]/', $message, $matches)) {
                        $template_line = $matches[1];
                    }
                    $deprecations[] = ['message' => $message, 'file' => $file, 'line' => $template_line];
                    return true;
                }
                return $prev_error_handler ? $prev_error_handler($level, $message, $file_name, $line) : false;
            });
        }
        $real_loader = $this->twig->get_loader();
        try {
            $temporary_loader = new Array_Loader([$file => $template]);
            $this->twig->set_loader($temporary_loader);
            $node_tree = $this->twig->parse($this->twig->tokenize(new Source($template, $file)));
            $this->twig->compile($node_tree);
            $this->twig->set_loader($real_loader);
        } catch (Error $e) {
            $this->twig->set_loader($real_loader);
            return ['template' => $template, 'file' => $file, 'line' => $e->get_template_line(), 'valid' => false, 'exception' => $e];
        } finally {
            if ($collect_deprecation) {
                restore_error_handler();
            }
        }
        return ['template' => $template, 'file' => $file, 'deprecations' => $deprecations, 'valid' => true];
    }
    private function display(Output_Interface $output, Symfony_Style $io, array $files): int
    {
        return match ($this->format) {
            'txt' => $this->display_txt($output, $io, $files),
            'json' => $this->display_json($output, $files),
            'github' => $this->display_txt($output, $io, $files, true),
            default => throw new InvalidArgumentException(\sprintf('Supported formats are "%s".', implode('", "', $this->get_available_format_options()))),
        };
    }
    private function display_txt(Output_Interface $output, Symfony_Style $io, array $files_info, bool $error_as_github_annotations = false): int
    {
        $errors = 0;
        $github_reporter = $error_as_github_annotations ? new Github_Action_Reporter($output) : null;
        $deprecations = array_merge(...array_column($files_info, 'deprecations'));
        foreach ($deprecations as $deprecation) {
            $this->render_deprecation($io, $deprecation['line'], $deprecation['message'], $deprecation['file'], $github_reporter);
        }
        foreach ($files_info as $info) {
            if ($info['valid'] && $output->is_verbose()) {
                $io->comment('<info>OK</info>' . ($info['file'] ? \sprintf(' in %s', $info['file']) : ''));
            } elseif (!$info['valid']) {
                ++$errors;
                $this->render_exception($io, $info['template'], $info['exception'], $info['file'], $github_reporter);
            }
        }
        if (0 === $errors) {
            $io->success(\sprintf('All %d Twig files contain valid syntax.', \count($files_info)));
        } else {
            $io->warning(\sprintf('%d Twig files have valid syntax and %d contain errors.', \count($files_info) - $errors, $errors));
        }
        return !$deprecations && !$errors ? 0 : 1;
    }
    private function display_json(Output_Interface $output, array $files_info): int
    {
        $errors = 0;
        array_walk($files_info, static function (array &$v) use (&$errors): void {
            $v['file'] = (string) $v['file'];
            unset($v['template']);
            if (!$v['valid']) {
                $v['message'] = $v['exception']->get_message();
                unset($v['exception']);
                ++$errors;
            }
        });
        $output->writeln(json_encode($files_info, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        return min($errors, 1);
    }
    private function render_deprecation(Symfony_Style $output, int $line, string $message, string $file, ?Github_Action_Reporter $github_reporter): void
    {
        $github_reporter?->error($message, $file, $line <= 0 ? null : $line);
        if ($file) {
            $output->text(\sprintf('<info> DEPRECATION </info> in %s (line %s)', $file, $line));
        } else {
            $output->text(\sprintf('<info> DEPRECATION </info> (line %s)', $line));
        }
        $output->text(\sprintf('<info> >> %s</info> ', $message));
    }
    private function render_exception(Symfony_Style $output, string $template, Error $exception, ?string $file = null, ?Github_Action_Reporter $github_reporter = null): void
    {
        $line = $exception->get_template_line();
        $github_reporter?->error($exception->get_raw_message(), $file, $line <= 0 ? null : $line);
        if ($file) {
            $output->text(\sprintf('<error> ERROR </error> in %s (line %s)', $file, $line));
        } else {
            $output->text(\sprintf('<error> ERROR </error> (line %s)', $line));
        }
        // If the line is not known (this might happen for deprecations if we fail at detecting the line for instance),
        // we render the message without context, to ensure the message is displayed.
        if ($line <= 0) {
            $output->text(\sprintf('<error> >> %s</error> ', $exception->get_raw_message()));
            return;
        }
        foreach ($this->get_context($template, $line) as $line_number => $code) {
            $output->text(\sprintf('%s %-6s %s', $line_number === $line ? '<error> >> </error>' : '    ', $line_number, $code));
            if ($line_number === $line) {
                $output->text(\sprintf('<error> >> %s</error> ', $exception->get_raw_message()));
            }
        }
    }
    private function get_context(string $template, int $line, int $context = 3): array
    {
        $lines = explode("\n", $template);
        $position = max(0, $line - $context);
        $max = min(\count($lines), $line - 1 + $context);
        $result = [];
        while ($position < $max) {
            $result[$position + 1] = $lines[$position];
            ++$position;
        }
        return $result;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_option_values_for('format')) {
            $suggestions->suggest_values($this->get_available_format_options());
        }
    }
    /** @return string[] */
    private function get_available_format_options(): array
    {
        return ['txt', 'json', 'github'];
    }
}
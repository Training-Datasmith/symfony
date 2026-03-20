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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Loader\Chain_Loader;
use Twig\Loader\Filesystem_Loader;
/**
 * Lists twig functions, filters, globals and tests present in the current project.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
#[As_Command(name: 'debug:twig', description: 'Show a list of twig functions, filters, globals and tests')]
class Debug_Command extends Command
{
    /**
     * @var FilesystemLoader[]
     */
    private array $filesystem_loaders;
    public function __construct(private readonly Environment $twig, private readonly ?string $project_dir = null, private readonly array $bundles_metadata = [], private readonly ?string $twig_default_path = null, private readonly ?File_Link_Formatter $file_link_formatter = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('name', Input_Argument::OPTIONAL, 'The template name'), new Input_Option('filter', null, Input_Option::VALUE_REQUIRED, 'Show details for all entries matching this filter'), new Input_Option('format', null, Input_Option::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', $this->get_available_format_options())), 'txt')])->set_help(<<<'EOF'
        The <info>%command.name%</info> command outputs a list of twig functions,
        filters, globals and tests.
        
          <info>php %command.full_name%</info>
        
        The command lists all functions, filters, etc.
        
          <info>php %command.full_name% @Twig/Exception/error.html.twig</info>
        
        The command lists all paths that match the given template name.
        
          <info>php %command.full_name% --filter=date</info>
        
        The command lists everything that contains the word date.
        
          <info>php %command.full_name% --format=json</info>
        
        The command lists everything in a machine readable json format.
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $name = $input->get_argument('name');
        $filter = $input->get_option('filter');
        if (null !== $name && [] === $this->get_filesystem_loaders()) {
            throw new InvalidArgumentException(\sprintf('Argument "name" not supported, it requires the Twig loader "%s".', Filesystem_Loader::class));
        }
        match ($input->get_option('format')) {
            'txt' => $name ? $this->display_paths_text($io, $name) : $this->display_general_text($io, $filter),
            'json' => $name ? $this->display_paths_json($io, $name) : $this->display_general_json($io, $filter),
            default => throw new InvalidArgumentException(\sprintf('Supported formats are "%s".', implode('", "', $this->get_available_format_options()))),
        };
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('name')) {
            $suggestions->suggest_values(array_keys($this->get_loader_paths()));
        }
        if ($input->must_suggest_option_values_for('format')) {
            $suggestions->suggest_values($this->get_available_format_options());
        }
    }
    private function display_paths_text(Symfony_Style $io, string $name): void
    {
        $file = new \ArrayIterator($this->find_template_files($name));
        $paths = $this->get_loader_paths($name);
        $io->section('Matched File');
        if ($file->valid()) {
            if ($file_link = $this->get_file_link($file->key())) {
                $io->block($file->current(), 'OK', \sprintf('fg=black;bg=green;href=%s', $file_link), ' ', true);
            } else {
                $io->success($file->current());
            }
            $file->next();
            if ($file->valid()) {
                $io->section('Overridden Files');
                do {
                    if ($file_link = $this->get_file_link($file->key())) {
                        $io->text(\sprintf('* <href=%s>%s</>', $file_link, $file->current()));
                    } else {
                        $io->text(\sprintf('* %s', $file->current()));
                    }
                    $file->next();
                } while ($file->valid());
            }
        } else {
            $alternatives = [];
            if ($paths) {
                $shortnames = [];
                $dirs = [];
                foreach (current($paths) as $path) {
                    $dirs[] = $this->is_absolute_path($path) ? $path : $this->project_dir . '/' . $path;
                }
                foreach (Finder::create()->files()->follow_links()->in($dirs) as $file) {
                    $shortnames[] = str_replace('\\', '/', $file->get_relative_pathname());
                }
                [$namespace, $shortname] = $this->parse_template_name($name);
                $alternatives = $this->find_alternatives($shortname, $shortnames);
                if (Filesystem_Loader::MAIN_NAMESPACE !== $namespace) {
                    $alternatives = array_map(static fn($shortname): string => '@' . $namespace . '/' . $shortname, $alternatives);
                }
            }
            $this->error($io, \sprintf('Template name "%s" not found', $name), $alternatives);
        }
        $io->section('Configured Paths');
        if ($paths) {
            $io->table(['Namespace', 'Paths'], $this->build_table_rows($paths));
        } else {
            $alternatives = [];
            $namespace = $this->parse_template_name($name)[0];
            if (Filesystem_Loader::MAIN_NAMESPACE === $namespace) {
                $message = 'No template paths configured for your application';
            } else {
                $message = \sprintf('No template paths configured for "@%s" namespace', $namespace);
                foreach ($this->get_filesystem_loaders() as $loader) {
                    $namespaces = $loader->get_namespaces();
                    foreach ($this->find_alternatives($namespace, $namespaces) as $namespace) {
                        $alternatives[] = '@' . $namespace;
                    }
                }
            }
            $this->error($io, $message, $alternatives);
            if (!$alternatives && $paths = $this->get_loader_paths()) {
                $io->table(['Namespace', 'Paths'], $this->build_table_rows($paths));
            }
        }
    }
    private function display_paths_json(Symfony_Style $io, string $name): void
    {
        $files = $this->find_template_files($name);
        $paths = $this->get_loader_paths($name);
        if ($files) {
            $data['matched_file'] = array_shift($files);
            if ($files) {
                $data['overridden_files'] = $files;
            }
        } else {
            $data['matched_file'] = \sprintf('Template name "%s" not found', $name);
        }
        $data['loader_paths'] = $paths;
        $io->writeln(json_encode($data));
    }
    private function display_general_text(Symfony_Style $io, ?string $filter = null): void
    {
        $decorated = $io->is_decorated();
        $types = ['functions', 'filters', 'tests', 'globals'];
        foreach ($types as $type) {
            $items = [];
            foreach ($this->twig->{'get' . ucfirst($type)}() as $name => $entity) {
                if (!$filter || str_contains((string) $name, $filter)) {
                    $items[$name] = $name . $this->get_pretty_metadata($type, $entity, $decorated);
                }
            }
            if (!$items) {
                continue;
            }
            $io->section(ucfirst($type));
            ksort($items);
            $io->listing($items);
        }
        if (!$filter && $paths = $this->get_loader_paths()) {
            $io->section('Loader Paths');
            $io->table(['Namespace', 'Paths'], $this->build_table_rows($paths));
        }
        if ($wrong_bundles = $this->find_wrong_bundle_overrides()) {
            foreach ($this->build_warning_messages($wrong_bundles) as $message) {
                $io->warning($message);
            }
        }
    }
    private function display_general_json(Symfony_Style $io, ?string $filter): void
    {
        $decorated = $io->is_decorated();
        $types = ['functions', 'filters', 'tests', 'globals'];
        $data = [];
        foreach ($types as $type) {
            foreach ($this->twig->{'get' . ucfirst($type)}() as $name => $entity) {
                if (!$filter || str_contains((string) $name, $filter)) {
                    $data[$type][$name] = $this->get_metadata($type, $entity);
                }
            }
        }
        if (isset($data['tests'])) {
            $data['tests'] = array_keys($data['tests']);
        }
        if (!$filter && $paths = $this->get_loader_paths($filter)) {
            $data['loader_paths'] = $paths;
        }
        if ($wrong_bundles = $this->find_wrong_bundle_overrides()) {
            $data['warnings'] = $this->build_warning_messages($wrong_bundles);
        }
        $data = json_encode($data, \JSON_PRETTY_PRINT);
        $io->writeln($decorated ? Output_Formatter::escape($data) : $data);
    }
    private function get_loader_paths(?string $name = null): array
    {
        $loader_paths = [];
        foreach ($this->get_filesystem_loaders() as $loader) {
            $namespaces = $loader->get_namespaces();
            if (null !== $name) {
                $namespace = $this->parse_template_name($name)[0];
                $namespaces = array_intersect([$namespace], $namespaces);
            }
            foreach ($namespaces as $namespace) {
                $paths = array_map($this->get_relative_path(...), $loader->get_paths($namespace));
                if (Filesystem_Loader::MAIN_NAMESPACE === $namespace) {
                    $namespace = '(None)';
                } else {
                    $namespace = '@' . $namespace;
                }
                $loader_paths[$namespace] = array_merge($loader_paths[$namespace] ?? [], $paths);
            }
        }
        return $loader_paths;
    }
    private function get_metadata(string $type, mixed $entity): mixed
    {
        if ('globals' === $type) {
            return $entity;
        }
        if ('tests' === $type) {
            return null;
        }
        if ('functions' === $type || 'filters' === $type) {
            $cb = $entity->get_callable();
            if (null === $cb) {
                return null;
            }
            if (\is_array($cb)) {
                if (!method_exists($cb[0], $cb[1])) {
                    return null;
                }
                $refl = new \ReflectionMethod($cb[0], $cb[1]);
            } elseif (\is_object($cb) && method_exists($cb, '__invoke')) {
                $refl = new \ReflectionMethod($cb, '__invoke');
            } elseif (\function_exists($cb)) {
                $refl = new \ReflectionFunction($cb);
            } elseif (\is_string($cb) && preg_match('{^(.+)::(.+)$}', $cb, $m) && method_exists($m[1], $m[2])) {
                $refl = new \ReflectionMethod($m[1], $m[2]);
            } else {
                throw new \UnexpectedValueException('Unsupported callback type.');
            }
            $args = $refl->get_parameters();
            // filter out context/environment args
            if ($entity->needs_environment()) {
                array_shift($args);
            }
            if ($entity->needs_context()) {
                array_shift($args);
            }
            if ('filters' === $type) {
                // remove the value the filter is applied on
                array_shift($args);
            }
            // format args
            return array_map(static function (\ReflectionParameter $param): string {
                if ($param->is_default_value_available()) {
                    return $param->get_name() . ' = ' . json_encode($param->get_default_value());
                }
                return $param->get_name();
            }, $args);
        }
        return null;
    }
    private function get_pretty_metadata(string $type, mixed $entity, bool $decorated): ?string
    {
        if ('tests' === $type) {
            return '';
        }
        try {
            $meta = $this->get_metadata($type, $entity);
            if (null === $meta) {
                return '(unknown?)';
            }
        } catch (\UnexpectedValueException $e) {
            return \sprintf(' <error>%s</error>', $decorated ? Output_Formatter::escape($e->get_message()) : $e->get_message());
        }
        if ('globals' === $type) {
            if (\is_object($meta)) {
                return ' = object(' . $meta::class . ')';
            }
            $description = substr(@json_encode($meta), 0, 50);
            return \sprintf(' = %s', $decorated ? Output_Formatter::escape($description) : $description);
        }
        if ('functions' === $type) {
            return '(' . implode(', ', $meta) . ')';
        }
        if ('filters' === $type) {
            return $meta ? '(' . implode(', ', $meta) . ')' : '';
        }
        return null;
    }
    private function find_wrong_bundle_overrides(): array
    {
        $alternatives = [];
        $bundle_names = [];
        if ($this->twig_default_path && $this->project_dir) {
            $folders = glob($this->twig_default_path . '/bundles/*', \GLOB_ONLYDIR);
            $relative_path = ltrim(substr($this->twig_default_path . '/bundles/', \strlen($this->project_dir)), \DIRECTORY_SEPARATOR);
            $bundle_names = array_reduce($folders, function (array $carry, $absolute_path) use ($relative_path): array {
                if (str_starts_with($absolute_path, (string) $this->project_dir)) {
                    $name = basename($absolute_path);
                    $path = ltrim($relative_path . $name, \DIRECTORY_SEPARATOR);
                    $carry[$name] = $path;
                }
                return $carry;
            }, $bundle_names);
        }
        if ($not_found_bundles = array_diff_key($bundle_names, $this->bundles_metadata)) {
            foreach ($not_found_bundles as $not_found_bundle => $path) {
                $alternatives[$path] = $this->find_alternatives($not_found_bundle, array_keys($this->bundles_metadata));
            }
        }
        return $alternatives;
    }
    private function build_warning_messages(array $wrong_bundles): array
    {
        $messages = [];
        foreach ($wrong_bundles as $path => $alternatives) {
            $message = \sprintf('Path "%s" not matching any bundle found', $path);
            if ($alternatives) {
                if (1 === \count($alternatives)) {
                    $message .= \sprintf(", did you mean \"%s\"?\n", $alternatives[0]);
                } else {
                    $message .= ", did you mean one of these:\n";
                    foreach ($alternatives as $bundle) {
                        $message .= \sprintf("  - %s\n", $bundle);
                    }
                }
            }
            $messages[] = trim($message);
        }
        return $messages;
    }
    private function error(Symfony_Style $io, string $message, array $alternatives = []): void
    {
        if ($alternatives) {
            if (1 === \count($alternatives)) {
                $message .= "\n\nDid you mean this?\n    ";
            } else {
                $message .= "\n\nDid you mean one of these?\n    ";
            }
            $message .= implode("\n    ", $alternatives);
        }
        $io->block($message, null, 'fg=white;bg=red', ' ', true);
    }
    private function find_template_files(string $name): array
    {
        [$namespace, $shortname] = $this->parse_template_name($name);
        $files = [];
        foreach ($this->get_filesystem_loaders() as $loader) {
            foreach ($loader->get_paths($namespace) as $path) {
                if (!$this->is_absolute_path($path)) {
                    $path = $this->project_dir . '/' . $path;
                }
                $filename = $path . '/' . $shortname;
                if (is_file($filename)) {
                    if (false !== $realpath = realpath($filename)) {
                        $files[$realpath] = $this->get_relative_path($realpath);
                    } else {
                        $files[$filename] = $this->get_relative_path($filename);
                    }
                }
            }
        }
        return $files;
    }
    private function parse_template_name(string $name, string $default = Filesystem_Loader::MAIN_NAMESPACE): array
    {
        if (isset($name[0]) && '@' === $name[0]) {
            if (false === ($pos = strpos($name, '/')) || $pos === \strlen($name) - 1) {
                throw new InvalidArgumentException(\sprintf('Malformed namespaced template name "%s" (expecting "@namespace/template_name").', $name));
            }
            $namespace = substr($name, 1, $pos - 1);
            $shortname = substr($name, $pos + 1);
            return [$namespace, $shortname];
        }
        return [$default, $name];
    }
    private function build_table_rows(array $loader_paths): array
    {
        $rows = [];
        $first_namespace = true;
        $prev_has_separator = false;
        foreach ($loader_paths as $namespace => $paths) {
            if (!$first_namespace && !$prev_has_separator && \count($paths) > 1) {
                $rows[] = ['', ''];
            }
            $first_namespace = false;
            foreach ($paths as $path) {
                $rows[] = [$namespace, $path . \DIRECTORY_SEPARATOR];
                $namespace = '';
            }
            if (\count($paths) > 1) {
                $rows[] = ['', ''];
                $prev_has_separator = true;
            } else {
                $prev_has_separator = false;
            }
        }
        if ($prev_has_separator) {
            array_pop($rows);
        }
        return $rows;
    }
    private function find_alternatives(string $name, array $collection): array
    {
        $alternatives = [];
        foreach ($collection as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= \strlen($name) / 3 || str_contains((string) $item, $name)) {
                $alternatives[$item] = isset($alternatives[$item]) ? $alternatives[$item] - $lev : $lev;
            }
        }
        $threshold = 1000.0;
        $alternatives = array_filter($alternatives, static fn(int $lev): bool => $lev < 2 * $threshold);
        ksort($alternatives, \SORT_NATURAL | \SORT_FLAG_CASE);
        return array_keys($alternatives);
    }
    private function get_relative_path(string $path): string
    {
        if (null !== $this->project_dir && str_starts_with($path, $this->project_dir)) {
            return ltrim(substr($path, \strlen($this->project_dir)), \DIRECTORY_SEPARATOR);
        }
        return $path;
    }
    private function is_absolute_path(string $file): bool
    {
        return strspn($file, '/\\', 0, 1) || \strlen($file) > 3 && ctype_alpha($file[0]) && ':' === $file[1] && strspn($file, '/\\', 2, 1) || parse_url($file, \PHP_URL_SCHEME);
    }
    /**
     * @return FilesystemLoader[]
     */
    private function get_filesystem_loaders(): array
    {
        if (isset($this->filesystem_loaders)) {
            return $this->filesystem_loaders;
        }
        $this->filesystem_loaders = [];
        $loader = $this->twig->get_loader();
        if ($loader instanceof Filesystem_Loader) {
            $this->filesystem_loaders[] = $loader;
        } elseif ($loader instanceof Chain_Loader) {
            foreach ($loader->get_loaders() as $l) {
                if ($l instanceof Filesystem_Loader) {
                    $this->filesystem_loaders[] = $l;
                }
            }
        }
        return $this->filesystem_loaders;
    }
    private function get_file_link(string $absolute_path): string
    {
        return (string) $this->file_link_formatter?->format($absolute_path, 1);
    }
    /** @return string[] */
    private function get_available_format_options(): array
    {
        return ['txt', 'json'];
    }
}
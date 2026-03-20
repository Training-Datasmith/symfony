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
namespace Symfony\Component\Dotenv\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Formatter\Output_Formatter;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Dotenv\Dotenv;
/**
 * A console command to debug current dotenv files with variables and values.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[As_Command(name: 'debug:dotenv', description: 'List all dotenv files with variables and values')]
final class Debug_Command extends Command
{
    public function __construct(private readonly string $kernel_environment, private readonly string $project_dir)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('filter', Input_Argument::OPTIONAL, 'The name of an environment variable or a filter.', null, $this->get_available_vars(...))])->set_help(<<<'EOT'
        The <info>%command.full_name%</info> command displays all the environment variables configured by dotenv:
        
          <info>php %command.full_name%</info>
        
        To get specific variables, specify its full or partial name:
        
            <info>php %command.full_name% FOO_BAR</info>
        
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $io->title('Dotenv Variables & Files');
        if (!\array_key_exists('SYMFONY_DOTENV_VARS', $_SERVER)) {
            $io->error('Dotenv component is not initialized.');
            return 1;
        }
        $dotenv_path = $this->get_dotenv_path();
        $env_files = $this->get_env_files($dotenv_path);
        $available_files = array_filter($env_files, is_file(...));
        if (\in_array(\sprintf('%s.local.php', $dotenv_path), $available_files, true)) {
            $io->warning(\sprintf('Due to existing dump file (%s.local.php) all other dotenv files are skipped.', $this->get_relative_name($dotenv_path)));
        }
        if (is_file($dotenv_path) && is_file(\sprintf('%s.dist', $dotenv_path))) {
            $io->warning(\sprintf('The file %s.dist gets skipped due to the existence of %1$s.', $this->get_relative_name($dotenv_path)));
        }
        $io->section('Scanned Files (in descending priority)');
        $io->listing(array_map(fn(string $env_file): string => \in_array($env_file, $available_files, true) ? \sprintf('<fg=green>✓</> %s', $this->get_relative_name($env_file)) : \sprintf('<fg=red>⨯</> %s', $this->get_relative_name($env_file)), $env_files));
        $name_filter = $input->get_argument('filter');
        $variables = $this->get_variables($available_files, $name_filter);
        $io->section('Variables');
        if ($variables || null === $name_filter) {
            $io->table(array_merge(['Variable', 'Value'], array_map($this->get_relative_name(...), $available_files)), $variables);
            $io->comment('Note that values might be different between web and CLI.');
        } else {
            $io->warning(\sprintf('No variables match the given filter "%s".', $name_filter));
        }
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('filter')) {
            $suggestions->suggest_values($this->get_available_vars());
        }
    }
    private function get_variables(array $env_files, ?string $name_filter): array
    {
        $variables = [];
        $file_values = [];
        $dotenv_vars = array_flip(explode(',', $_SERVER['SYMFONY_DOTENV_VARS'] ?? ''));
        foreach ($env_files as $env_file) {
            $file_values[$env_file] = $this->load_values($env_file);
            $variables += $file_values[$env_file];
        }
        foreach ($variables as $var => $var_details) {
            if (null !== $name_filter && 0 !== stripos((string) $var, $name_filter)) {
                unset($variables[$var]);
                continue;
            }
            $real_value = $_SERVER[$var] ?? '';
            $var_details = [$var, '<fg=green>' . Output_Formatter::escape($real_value) . '</>'];
            $var_seen = !isset($dotenv_vars[$var]);
            foreach ($env_files as $env_file) {
                if (null === $value = $file_values[$env_file][$var] ?? null) {
                    $var_details[] = '<fg=yellow>n/a</>';
                    continue;
                }
                $shortened_value = Output_Formatter::escape($this->get_helper('formatter')->truncate($value, 30));
                $var_details[] = $value === $real_value && !$var_seen ? '<fg=green>' . $shortened_value . '</>' : $shortened_value;
                $var_seen = $var_seen || $value === $real_value;
            }
            $variables[$var] = $var_details;
        }
        ksort($variables);
        return $variables;
    }
    private function get_available_vars(): array
    {
        $env_files = $this->get_env_files($this->get_dotenv_path());
        return array_keys($this->get_variables(array_filter($env_files, is_file(...)), null));
    }
    private function get_dotenv_path(): string
    {
        $config = [];
        $project_dir = $this->project_dir;
        if (is_file($project_dir)) {
            $config = ['dotenv_path' => basename($project_dir)];
            $project_dir = \dirname($project_dir);
        }
        $composer_file = $project_dir . '/composer.json';
        $config += $_SERVER['APP_RUNTIME_OPTIONS'] ?? (is_file($composer_file) ? json_decode(file_get_contents($composer_file), true) : [])['extra']['runtime'] ?? [];
        return $project_dir . '/' . ($config['dotenv_path'] ?? '.env');
    }
    private function get_env_files(string $file_path): array
    {
        $files = [\sprintf('%s.local.php', $file_path), \sprintf('%s.%s.local', $file_path, $this->kernel_environment), \sprintf('%s.%s', $file_path, $this->kernel_environment)];
        if ('test' !== $this->kernel_environment) {
            $files[] = \sprintf('%s.local', $file_path);
        }
        if (!is_file($file_path) && is_file(\sprintf('%s.dist', $file_path))) {
            $files[] = \sprintf('%s.dist', $file_path);
        } else {
            $files[] = $file_path;
        }
        return $files;
    }
    private function get_relative_name(string $file_path): string
    {
        $project_dir = is_file($this->project_dir) ? \dirname($this->project_dir) : $this->project_dir;
        if (str_starts_with($file_path, $project_dir . '/') || str_starts_with($file_path, $project_dir . \DIRECTORY_SEPARATOR)) {
            return substr($file_path, \strlen($project_dir) + 1);
        }
        return basename($file_path);
    }
    private function load_values(string $file_path): array
    {
        if (str_ends_with($file_path, '.php')) {
            return include $file_path;
        }
        return (new Dotenv())->parse(file_get_contents($file_path));
    }
}
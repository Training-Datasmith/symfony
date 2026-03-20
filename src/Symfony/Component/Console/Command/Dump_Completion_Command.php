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
namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Process\Process;
/**
 * Dumps the completion script for the current shell.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
#[As_Command(name: 'completion', description: 'Dump the shell completion script')]
final class Dump_Completion_Command extends Command
{
    private array $supported_shells;
    protected function configure(): void
    {
        $full_command = $_SERVER['PHP_SELF'];
        $command_name = basename((string) $full_command);
        $full_command = @realpath($full_command) ?: $full_command;
        $shell = self::guess_shell();
        [$rc_file, $completion_file] = match ($shell) {
            'fish' => ['~/.config/fish/config.fish', "/etc/fish/completions/{$command_name}.fish"],
            'zsh' => ['~/.zshrc', '$fpath[1]/_' . $command_name],
            default => ['~/.bashrc', "/etc/bash_completion.d/{$command_name}"],
        };
        $supported_shells = implode(', ', $this->get_supported_shells());
        $this->set_help(<<<EOH
        The <info>%command.name%</> command dumps the shell completion script required
        to use shell autocompletion (currently, {$supported_shells} completion are supported).
        
        <comment>Static installation
        -------------------</>
        
        Dump the script to a global completion file and restart your shell:
        
            <info>%command.full_name% {$shell} | sudo tee {$completion_file}</>
        
        Or dump the script to a local file and source it:
        
            <info>%command.full_name% {$shell} > completion.sh</>
        
            <comment># source the file whenever you use the project</>
            <info>source completion.sh</>
        
            <comment># or add this line at the end of your "{$rc_file}" file:</>
            <info>source /path/to/completion.sh</>
        
        <comment>Dynamic installation
        --------------------</>
        
        Add this to the end of your shell configuration file (e.g. <info>"{$rc_file}"</>):
        
            <info>eval "\$({$full_command} completion {$shell})"</>
        EOH)->add_argument('shell', Input_Argument::OPTIONAL, 'The shell type (e.g. "bash"), the value of the "$SHELL" env var will be used if this is not given', null, $this->get_supported_shells(...))->add_option('debug', null, Input_Option::VALUE_NONE, 'Tail the completion debug log');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $command_name = basename((string) $_SERVER['argv'][0]);
        if ($input->get_option('debug')) {
            $this->tail_debug_log($command_name, $output);
            return 0;
        }
        $shell = $input->get_argument('shell') ?? self::guess_shell();
        $completion_file = __DIR__ . '/../Resources/completion.' . $shell;
        if (!file_exists($completion_file)) {
            $supported_shells = $this->get_supported_shells();
            if ($output instanceof Console_Output_Interface) {
                $output = $output->get_error_output();
            }
            if ($shell) {
                $output->writeln(\sprintf('<error>Detected shell "%s", which is not supported by Symfony shell completion (supported shells: "%s").</>', $shell, implode('", "', $supported_shells)));
            } else {
                $output->writeln(\sprintf('<error>Shell not detected, Symfony shell completion only supports "%s").</>', implode('", "', $supported_shells)));
            }
            return 2;
        }
        $output->write(str_replace(['{{ COMMAND_NAME }}', '{{ VERSION }}'], [$command_name, Complete_Command::COMPLETION_API_VERSION], file_get_contents($completion_file)));
        return 0;
    }
    private static function guess_shell(): string
    {
        return basename($_SERVER['SHELL'] ?? '');
    }
    private function tail_debug_log(string $command_name, Output_Interface $output): void
    {
        $debug_file = sys_get_temp_dir() . '/sf_' . $command_name . '.log';
        if (!file_exists($debug_file)) {
            touch($debug_file);
        }
        $process = new Process(['tail', '-f', $debug_file], null, null, null, 0);
        $process->run(static function (string $type, string $line) use ($output): void {
            $output->write($line);
        });
    }
    /**
     * @return string[]
     */
    private function get_supported_shells(): array
    {
        if (isset($this->supported_shells)) {
            return $this->supported_shells;
        }
        $shells = [];
        foreach (new \Directory_Iterator(__DIR__ . '/../Resources/') as $file) {
            if (str_starts_with($file->get_basename(), 'completion.') && $file->is_file()) {
                $shells[] = $file->get_extension();
            }
        }
        sort($shells);
        return $this->supported_shells = $shells;
    }
}
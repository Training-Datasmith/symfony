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
namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\Command_Not_Found_Exception;
/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Application_Description
{
    public const GLOBAL_NAMESPACE = '_global';
    private array $namespaces;
    /**
     * @var array<string, Command>
     */
    private array $commands;
    /**
     * @var array<string, Command>
     */
    private array $aliases = [];
    public function __construct(private readonly Application $application, private readonly ?string $namespace = null, private readonly bool $show_hidden = false)
    {
    }
    public function get_namespaces(): array
    {
        if (!isset($this->namespaces)) {
            $this->inspect_application();
        }
        return $this->namespaces;
    }
    /**
     * @return Command[]
     */
    public function get_commands(): array
    {
        if (!isset($this->commands)) {
            $this->inspect_application();
        }
        return $this->commands;
    }
    /**
     * @throws CommandNotFoundException
     */
    public function get_command(string $name): Command
    {
        if (!isset($this->commands[$name]) && !isset($this->aliases[$name])) {
            throw new Command_Not_Found_Exception(\sprintf('Command "%s" does not exist.', $name));
        }
        return $this->commands[$name] ?? $this->aliases[$name];
    }
    private function inspect_application(): void
    {
        $this->commands = [];
        $this->namespaces = [];
        $all = $this->application->all($this->namespace ? $this->application->find_namespace($this->namespace) : null);
        foreach ($this->sort_commands($all) as $namespace => $commands) {
            $names = [];
            foreach ($commands as $name => $command) {
                if (!$command->get_name()) {
                    continue;
                }
                if (!$this->show_hidden && $command->is_hidden()) {
                    continue;
                }
                if ($command->get_name() === $name) {
                    $this->commands[$name] = $command;
                } else {
                    $this->aliases[$name] = $command;
                }
                $names[] = $name;
            }
            $this->namespaces[$namespace] = ['id' => $namespace, 'commands' => $names];
        }
    }
    /**
     * @return array<string, array<string, Command>>
     */
    private function sort_commands(array $commands): array
    {
        $namespaced_commands = [];
        $global_commands = [];
        $sorted_commands = [];
        foreach ($commands as $name => $command) {
            $key = $this->application->extract_namespace($name, 1);
            if (\in_array($key, ['', self::GLOBAL_NAMESPACE], true)) {
                $global_commands[$name] = $command;
            } else {
                $namespaced_commands[$key][$name] = $command;
            }
        }
        if ($global_commands) {
            ksort($global_commands);
            $sorted_commands[self::GLOBAL_NAMESPACE] = $global_commands;
        }
        if ($namespaced_commands) {
            ksort($namespaced_commands, \SORT_STRING);
            foreach ($namespaced_commands as $key => $commands_set) {
                ksort($commands_set);
                $sorted_commands[$key] = $commands_set;
            }
        }
        return $sorted_commands;
    }
}
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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Helper\Helper_Interface;
use Symfony\Component\Console\Helper\Helper_Set;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Lazy_Command extends Command
{
    public function __construct(string $name, array $aliases, string $description, bool $is_hidden, private \Closure|Command $command, private readonly ?bool $is_enabled = true)
    {
        $this->set_name($name)->set_aliases($aliases)->set_hidden($is_hidden)->set_description($description);
    }
    public function ignore_validation_errors(): void
    {
        $this->get_command()->ignore_validation_errors();
    }
    public function set_application(?Application $application): void
    {
        $this->command->set_application($application);
        parent::set_application($application);
    }
    public function set_helper_set(Helper_Set $helper_set): void
    {
        $this->command->set_helper_set($helper_set);
        parent::set_helper_set($helper_set);
    }
    public function is_enabled(): bool
    {
        return $this->is_enabled ?? $this->get_command()->is_enabled();
    }
    public function run(Input_Interface $input, Output_Interface $output): int
    {
        return $this->get_command()->run($input, $output);
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        $this->get_command()->complete($input, $suggestions);
    }
    public function set_code(callable $code): static
    {
        $this->get_command()->set_code($code);
        return $this;
    }
    /**
     * @internal
     */
    public function merge_application_definition(bool $merge_args = true): void
    {
        $this->get_command()->merge_application_definition($merge_args);
    }
    public function set_definition(array|Input_Definition $definition): static
    {
        $this->get_command()->set_definition($definition);
        return $this;
    }
    public function get_definition(): Input_Definition
    {
        return $this->get_command()->get_definition();
    }
    public function get_native_definition(): Input_Definition
    {
        return $this->get_command()->get_native_definition();
    }
    /**
     * @param array|\Closure(CompletionInput,CompletionSuggestions):list<string|Suggestion> $suggestedValues The values used for input completion
     */
    public function add_argument(string $name, ?int $mode = null, string $description = '', mixed $default = null, array|\Closure $suggested_values = []): static
    {
        $this->get_command()->add_argument($name, $mode, $description, $default, $suggested_values);
        return $this;
    }
    /**
     * @param array|\Closure(CompletionInput,CompletionSuggestions):list<string|Suggestion> $suggestedValues The values used for input completion
     */
    public function add_option(string $name, string|array|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array|\Closure $suggested_values = []): static
    {
        $this->get_command()->add_option($name, $shortcut, $mode, $description, $default, $suggested_values);
        return $this;
    }
    public function set_process_title(string $title): static
    {
        $this->get_command()->set_process_title($title);
        return $this;
    }
    public function set_help(string $help): static
    {
        $this->get_command()->set_help($help);
        return $this;
    }
    public function get_help(): string
    {
        return $this->get_command()->get_help();
    }
    public function get_processed_help(): string
    {
        return $this->get_command()->get_processed_help();
    }
    public function get_synopsis(bool $short = false): string
    {
        return $this->get_command()->get_synopsis($short);
    }
    public function add_usage(string $usage): static
    {
        $this->get_command()->add_usage($usage);
        return $this;
    }
    public function get_usages(): array
    {
        return $this->get_command()->get_usages();
    }
    public function get_helper(string $name): Helper_Interface
    {
        return $this->get_command()->get_helper($name);
    }
    public function get_command(): parent
    {
        if (!$this->command instanceof \Closure) {
            return $this->command;
        }
        $command = $this->command = ($this->command)();
        $command->set_application($this->get_application());
        if (null !== $this->get_helper_set()) {
            $command->set_helper_set($this->get_helper_set());
        }
        $command->set_name($this->get_name())->set_aliases($this->get_aliases())->set_hidden($this->is_hidden())->set_description($this->get_description());
        // Will throw if the command is not correctly initialized.
        $command->get_definition();
        return $command;
    }
}
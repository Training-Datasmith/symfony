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
namespace Symfony\Bundle\Framework_Bundle\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\List_Command;
use Symfony\Component\Console\Command\Traceable_Command;
use Symfony\Component\Console\Debug\Cli_Request;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Http_Kernel\Bundle\Bundle;
use Symfony\Component\Http_Kernel\Kernel;
use Symfony\Component\Http_Kernel\Kernel_Interface;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Application extends Base_Application
{
    private bool $commands_registered = false;
    private array $registration_errors = [];
    public function __construct(private readonly Kernel_Interface $kernel)
    {
        parent::__construct('Symfony', Kernel::VERSION);
        $input_definition = $this->get_definition();
        $input_definition->add_option(new Input_Option('--env', '-e', Input_Option::VALUE_REQUIRED, 'The Environment name.', $kernel->get_environment()));
        $input_definition->add_option(new Input_Option('--no-debug', null, Input_Option::VALUE_NONE, 'Switch off debug mode.'));
        $input_definition->add_option(new Input_Option('--profile', null, Input_Option::VALUE_NONE, 'Enables profiling (requires debug).'));
    }
    /**
     * Gets the Kernel associated with this Console.
     */
    public function get_kernel(): Kernel_Interface
    {
        return $this->kernel;
    }
    public function reset(): void
    {
        if ($this->kernel->get_container()->has('services_resetter')) {
            $this->kernel->get_container()->get('services_resetter')->reset();
        }
    }
    /**
     * Runs the current application.
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function do_run(Input_Interface $input, Output_Interface $output): int
    {
        $this->register_commands();
        if ($this->registration_errors) {
            $this->render_registration_errors($input, $output);
        }
        $container = $this->kernel->get_container();
        $this->set_dispatcher($container->get('event_dispatcher'));
        if ($container->has('console.argument_resolver')) {
            $this->set_argument_resolver($container->get('console.argument_resolver'));
        }
        return parent::do_run($input, $output);
    }
    protected function do_run_command(Command $command, Input_Interface $input, Output_Interface $output): int
    {
        $request_stack = null;
        $render_registration_errors = true;
        if (!$command instanceof List_Command) {
            if ($this->registration_errors) {
                $this->render_registration_errors($input, $output);
                $this->registration_errors = [];
                $render_registration_errors = false;
            }
        }
        if ($input->has_parameter_option('--profile')) {
            $container = $this->kernel->get_container();
            if (!$this->kernel->is_debug()) {
                if ($output instanceof Console_Output_Interface) {
                    $output = $output->get_error_output();
                }
                (new Symfony_Style($input, $output))->warning('Debug mode should be enabled when the "--profile" option is used.');
            } elseif (!$container->has('debug.stopwatch')) {
                if ($output instanceof Console_Output_Interface) {
                    $output = $output->get_error_output();
                }
                (new Symfony_Style($input, $output))->warning('The "--profile" option needs the Stopwatch component. Try running "composer require symfony/stopwatch".');
            } elseif (!$container->has('.virtual_request_stack')) {
                if ($output instanceof Console_Output_Interface) {
                    $output = $output->get_error_output();
                }
                (new Symfony_Style($input, $output))->warning('The "--profile" option needs the profiler integration. Try enabling the "framework.profiler" option.');
            } else {
                $command = new Traceable_Command($command, $container->get('debug.stopwatch'));
                $request_stack = $container->get('.virtual_request_stack');
                $request_stack->push(new Cli_Request($command));
            }
        }
        try {
            $return_code = parent::do_run_command($command, $input, $output);
        } finally {
            $request_stack?->pop();
        }
        if ($render_registration_errors && $this->registration_errors) {
            $this->render_registration_errors($input, $output);
            $this->registration_errors = [];
        }
        return $return_code;
    }
    public function find(string $name): Command
    {
        $this->register_commands();
        return parent::find($name);
    }
    public function get(string $name): Command
    {
        $this->register_commands();
        return parent::get($name);
    }
    public function all(?string $namespace = null): array
    {
        $this->register_commands();
        return parent::all($namespace);
    }
    public function get_long_version(): string
    {
        return parent::get_long_version() . \sprintf(' (env: <comment>%s</>, debug: <comment>%s</>)', $this->kernel->get_environment(), $this->kernel->is_debug() ? 'true' : 'false');
    }
    public function add_command(callable|Command $command): ?Command
    {
        $this->register_commands();
        return parent::add_command($command);
    }
    protected function register_commands(): void
    {
        if ($this->commands_registered) {
            return;
        }
        $this->commands_registered = true;
        $this->kernel->boot();
        $container = $this->kernel->get_container();
        foreach ($this->kernel->get_bundles() as $bundle) {
            if ($bundle instanceof Bundle) {
                try {
                    $bundle->register_commands($this);
                } catch (\Throwable $e) {
                    $this->registration_errors[] = $e;
                }
            }
        }
        if ($container->has('console.command_loader')) {
            $this->set_command_loader($container->get('console.command_loader'));
        }
        if ($container->has_parameter('console.command.ids')) {
            $lazy_command_ids = $container->has_parameter('console.lazy_command.ids') ? $container->get_parameter('console.lazy_command.ids') : [];
            foreach ($container->get_parameter('console.command.ids') as $id) {
                if (!isset($lazy_command_ids[$id])) {
                    try {
                        $this->add_command($container->get($id));
                    } catch (\Throwable $e) {
                        $this->registration_errors[] = $e;
                    }
                }
            }
        }
    }
    private function render_registration_errors(Input_Interface $input, Output_Interface $output): void
    {
        if ($output instanceof Console_Output_Interface) {
            $output = $output->get_error_output();
        }
        (new Symfony_Style($input, $output))->warning('Some commands could not be registered:');
        foreach ($this->registration_errors as $error) {
            $this->do_render_throwable($error, $output);
        }
    }
}
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
use Symfony\Component\Console\Argument_Resolver\Argument_Resolver;
use Symfony\Component\Console\Argument_Resolver\Argument_Resolver_Interface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\Map_Input;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Interaction\Interaction;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * Represents an invokable command.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 *
 * @internal
 */
class Invokable_Command implements Signalable_Command_Interface
{
    private readonly ?Signalable_Command_Interface $signalable_command;
    private readonly \ReflectionFunction $invokable;
    /**
     * @var list<Interaction>|null
     */
    private ?array $interactions = null;
    private $code;
    public function __construct(private readonly Command $command, callable $code, private ?Argument_Resolver_Interface $argument_resolver = null)
    {
        $this->code = $code;
        $this->signalable_command = $code instanceof Signalable_Command_Interface ? $code : null;
        $this->invokable = new \ReflectionFunction($this->get_closure($code));
    }
    /**
     * Invokes a callable with parameters generated from the input interface.
     */
    public function __invoke(Input_Interface $input, Output_Interface $output): int
    {
        $status_code = $this->invokable->invoke(...$this->get_parameters($this->invokable, $input, $output));
        if (!\is_int($status_code)) {
            throw new \TypeError(\sprintf('The command "%s" must return an integer value in the "%s" method, but "%s" was returned.', $this->command->get_name(), $this->invokable->get_name(), get_debug_type($status_code)));
        }
        return $status_code;
    }
    /**
     * Configures the input definition from an invokable-defined function.
     *
     * Processes the parameters of the reflection function to extract and
     * add arguments or options to the provided input definition.
     */
    public function configure(Input_Definition $definition): void
    {
        foreach ($this->invokable->get_parameters() as $parameter) {
            if ($argument = Argument::try_from($parameter)) {
                $definition->add_argument($argument->to_input_argument());
                continue;
            }
            if ($option = Option::try_from($parameter)) {
                $definition->add_option($option->to_input_option());
                continue;
            }
            if ($input = Map_Input::try_from($parameter)) {
                $input_arguments = array_map(static fn(Argument $a): \Symfony\Component\Console\Input\Input_Argument => $a->to_input_argument(), iterator_to_array($input->get_arguments(), false));
                // make sure optional arguments are defined after required ones
                usort($input_arguments, static fn(Input_Argument $a, Input_Argument $b): int => (int) $b->is_required() - (int) $a->is_required());
                foreach ($input_arguments as $input_argument) {
                    $definition->add_argument($input_argument);
                }
                foreach ($input->get_options() as $option) {
                    $definition->add_option($option->to_input_option());
                }
            }
        }
    }
    public function get_code(): callable
    {
        return $this->code;
    }
    private function get_closure(callable $code): \Closure
    {
        if (!$code instanceof \Closure) {
            return $code(...);
        }
        if (null !== (new \ReflectionFunction($code))->get_closure_this()) {
            return $code;
        }
        set_error_handler(static function (): void {
        });
        try {
            if ($c = \Closure::bind($code, $this->command)) {
                $code = $c;
            }
        } finally {
            restore_error_handler();
        }
        return $code;
    }
    private function get_parameters(\ReflectionFunction $function, Input_Interface $input, Output_Interface $output): array
    {
        $core_utilities = [];
        $needs_argument_resolver = false;
        foreach ($function->get_parameters() as $index => $param) {
            $type = $param->get_type();
            if ($type instanceof \ReflectionNamedType) {
                $argument = match ($type->get_name()) {
                    Input_Interface::class => $input,
                    Output_Interface::class => $output,
                    Symfony_Style::class => new Symfony_Style($input, $output, $this->command->get_application()?->get_dispatcher()),
                    Cursor::class => new Cursor($output),
                    Application::class => $this->command->get_application(),
                    Command::class, self::class => $this->command,
                    default => null,
                };
                if (null !== $argument) {
                    $core_utilities[$index] = $argument;
                    continue;
                }
            }
            $needs_argument_resolver = true;
        }
        if (!$needs_argument_resolver) {
            return $core_utilities;
        }
        if (null === $this->argument_resolver) {
            $this->argument_resolver = $this->command->get_application()?->get_argument_resolver() ?? new Argument_Resolver(Argument_Resolver::get_default_argument_value_resolvers());
        }
        $closure = $function->get_closure();
        $resolved_args = $this->argument_resolver->get_arguments($input, $closure, $function);
        $parameters = [];
        $resolved_index = 0;
        foreach ($function->get_parameters() as $index => $param) {
            if (isset($core_utilities[$index])) {
                $parameters[] = $core_utilities[$index];
            } elseif ($param->is_variadic()) {
                // Variadic parameters consume all remaining resolved arguments
                $parameters = [...$parameters, ...\array_slice($resolved_args, $resolved_index)];
                break;
            } else {
                $parameters[] = $resolved_args[$resolved_index++] ?? null;
            }
        }
        return $parameters;
    }
    public function get_subscribed_signals(): array
    {
        return $this->signalable_command?->get_subscribed_signals() ?? [];
    }
    public function handle_signal(int $signal, int|false $previous_exit_code = 0): int|false
    {
        return $this->signalable_command?->handle_signal($signal, $previous_exit_code) ?? false;
    }
    public function is_interactive(): bool
    {
        if (null === $this->interactions) {
            $this->collect_interactions();
        }
        return [] !== $this->interactions;
    }
    public function interact(Input_Interface $input, Output_Interface $output): void
    {
        if (null === $this->interactions) {
            $this->collect_interactions();
        }
        foreach ($this->interactions as $interaction) {
            $interaction->interact($input, $output, $this->get_parameters(...));
        }
    }
    private function collect_interactions(): void
    {
        $invokable_this = $this->invokable->get_closure_this();
        $this->interactions = [];
        foreach ($this->invokable->get_parameters() as $parameter) {
            if ($spec = Argument::try_from($parameter)) {
                if ($attribute = $spec->get_interactive_attribute()) {
                    $this->interactions[] = new Interaction($invokable_this, $attribute);
                }
                continue;
            }
            if ($spec = Map_Input::try_from($parameter)) {
                $this->interactions = [...$this->interactions, ...$spec->get_property_interactions(), ...$spec->get_method_interactions()];
            }
        }
        if (!$class = $this->invokable->get_closure_called_class()) {
            return;
        }
        foreach ($class->get_methods() as $method) {
            if ($attribute = Interact::try_from($method)) {
                $this->interactions[] = new Interaction($invokable_this, $attribute);
            }
        }
    }
}
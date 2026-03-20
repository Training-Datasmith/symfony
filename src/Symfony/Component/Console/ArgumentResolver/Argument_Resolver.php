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
namespace Symfony\Component\Console\Argument_Resolver;

use Psr\Container\Container_Interface;
use Symfony\Component\Console\Argument_Resolver\Exception\Near_Miss_Value_Resolver_Exception;
use Symfony\Component\Console\Argument_Resolver\Exception\Resolver_Not_Found_Exception;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver as Resolver;
use Symfony\Component\Console\Argument_Resolver\Value_Resolver\Value_Resolver_Interface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\Reflection_Member;
use Symfony\Component\Console\Attribute\Value_Resolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Contracts\Service\Service_Provider_Interface;
/**
 * Resolves the arguments passed to a console command.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final readonly class Argument_Resolver implements Argument_Resolver_Interface
{
    /**
     * @param iterable<mixed, ValueResolverInterface> $argumentValueResolvers
     */
    public function __construct(private iterable $argument_value_resolvers = [], private ?Container_Interface $named_resolvers = null)
    {
    }
    public function get_arguments(Input_Interface $input, callable $command, ?\Reflection_Function_Abstract $reflector = null): array
    {
        $reflector ??= new \ReflectionFunction($command(...));
        $argument_reflectors = [];
        foreach ($reflector->get_parameters() as $param) {
            $argument_reflectors[$param->get_name()] = new Reflection_Member($param);
        }
        $arguments = [];
        foreach ($argument_reflectors as $argument_name => $member) {
            $argument_value_resolvers = $this->argument_value_resolvers;
            $disabled_resolvers = [];
            if ($this->named_resolvers && $attributes = $member->get_attributes(Value_Resolver::class)) {
                $resolver_name = null;
                foreach ($attributes as $attribute) {
                    if ($attribute->disabled) {
                        $disabled_resolvers[$attribute->resolver] = true;
                    } elseif ($resolver_name) {
                        throw new \LogicException(\sprintf('You can only pin one resolver per argument, but argument "$%s" of "%s()" has more.', $member->get_name(), $member->get_source_name()));
                    } else {
                        $resolver_name = $attribute->resolver;
                    }
                }
                if ($resolver_name) {
                    if (!$this->named_resolvers->has($resolver_name)) {
                        throw new Resolver_Not_Found_Exception($resolver_name, $this->named_resolvers instanceof Service_Provider_Interface ? array_keys($this->named_resolvers->get_provided_services()) : []);
                    }
                    $argument_value_resolvers = [$this->named_resolvers->get($resolver_name)];
                }
            }
            $value_resolver_exceptions = [];
            foreach ($argument_value_resolvers as $name => $resolver) {
                if (isset($disabled_resolvers[\is_int($name) ? $resolver::class : $name])) {
                    continue;
                }
                try {
                    $count = 0;
                    foreach ($resolver->resolve($argument_name, $input, $member) as $argument) {
                        ++$count;
                        $arguments[] = $argument;
                    }
                } catch (Near_Miss_Value_Resolver_Exception $e) {
                    $value_resolver_exceptions[] = $e;
                }
                if (1 < $count && !$member->is_variadic()) {
                    throw new \InvalidArgumentException(\sprintf('"%s::resolve()" must yield at most one value for non-variadic arguments.', get_debug_type($resolver)));
                }
                if ($count) {
                    continue 2;
                }
            }
            // For variadic parameters with explicit input mapping, 0 values is valid
            if ($member->is_variadic() && (Argument::try_from($member->get_member()) || Option::try_from($member->get_member()))) {
                continue;
            }
            $type = $member->get_type();
            $type_name = $type instanceof \ReflectionNamedType ? $type->get_name() : null;
            if ($type_name && \in_array($type_name, [Input_Interface::class, Output_Interface::class, Symfony_Style::class, Cursor::class, \Symfony\Component\Console\Application::class, Command::class], true)) {
                continue;
            }
            $reasons = array_map(static fn(Near_Miss_Value_Resolver_Exception $e): string => $e->get_message(), $value_resolver_exceptions);
            if (!$reasons) {
                $reasons[] = \sprintf('The parameter has no #[Argument], #[Option], or #[MapInput] attribute, and its type "%s" cannot be auto-resolved.', $type_name ?? 'unknown');
                $reasons[] = 'Add an attribute to map this parameter to command input.';
            }
            throw new \RuntimeException(\sprintf('Could not resolve parameter "$%s" of command "%s".' . "\n\n" . 'Possible reasons:' . "\n" . '  • ' . implode("\n  • ", $reasons), $member->get_name(), $member->get_source_name()));
        }
        return $arguments;
    }
    /**
     * @return iterable<int, ValueResolverInterface>
     */
    public static function get_default_argument_value_resolvers(): iterable
    {
        $builtin_type_resolver = new Resolver\Builtin_Type_Value_Resolver();
        $backed_enum_resolver = new Resolver\Backed_Enum_Value_Resolver();
        $date_time_resolver = new Resolver\Date_Time_Value_Resolver();
        $input_file_resolver = new Resolver\Input_File_Value_Resolver();
        return [$backed_enum_resolver, new Resolver\Uid_Value_Resolver(), $input_file_resolver, $builtin_type_resolver, new Resolver\Map_Input_Value_Resolver($builtin_type_resolver, $backed_enum_resolver, $date_time_resolver), $date_time_resolver, new Resolver\Default_Value_Resolver(), new Resolver\Variadic_Value_Resolver()];
    }
}
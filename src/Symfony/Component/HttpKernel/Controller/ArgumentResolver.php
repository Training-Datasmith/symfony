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
namespace Symfony\Component\Http_Kernel\Controller;

use Psr\Container\Container_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Attribute\Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Default_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Request_Attribute_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Request_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Session_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller\Argument_Resolver\Variadic_Value_Resolver;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata_Factory;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata_Factory_Interface;
use Symfony\Component\Http_Kernel\Exception\Near_Miss_Value_Resolver_Exception;
use Symfony\Component\Http_Kernel\Exception\Resolver_Not_Found_Exception;
use Symfony\Contracts\Service\Service_Provider_Interface;
/**
 * Responsible for resolving the arguments passed to an action.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final readonly class Argument_Resolver implements Argument_Resolver_Interface
{
    private iterable $argument_value_resolvers;
    /**
     * @param iterable<mixed, ValueResolverInterface> $argumentValueResolvers
     */
    public function __construct(private ?Argument_Metadata_Factory_Interface $argument_metadata_factory = new Argument_Metadata_Factory(), iterable $argument_value_resolvers = [], private ?Container_Interface $named_resolvers = null)
    {
        $this->argument_value_resolvers = $argument_value_resolvers ?: self::get_default_argument_value_resolvers();
    }
    public function get_arguments(Request $request, callable $controller, ?\Reflection_Function_Abstract $reflector = null): array
    {
        $arguments = [];
        foreach ($this->argument_metadata_factory->create_argument_metadata($controller, $reflector) as $metadata) {
            $argument_value_resolvers = $this->argument_value_resolvers;
            $disabled_resolvers = [];
            if ($this->named_resolvers && $attributes = $metadata->get_attributes_of_type(Value_Resolver::class, $metadata::IS_INSTANCEOF)) {
                $resolver_name = null;
                foreach ($attributes as $attribute) {
                    if ($attribute->disabled) {
                        $disabled_resolvers[$attribute->resolver] = true;
                    } elseif ($resolver_name) {
                        throw new \LogicException(\sprintf('You can only pin one resolver per argument, but argument "$%s" of "%s()" has more.', $metadata->get_name(), $metadata->get_controller_name()));
                    } else {
                        $resolver_name = $attribute->resolver;
                    }
                }
                if ($resolver_name) {
                    if (!$this->named_resolvers->has($resolver_name)) {
                        throw new Resolver_Not_Found_Exception($resolver_name, $this->named_resolvers instanceof Service_Provider_Interface ? array_keys($this->named_resolvers->get_provided_services()) : []);
                    }
                    $argument_value_resolvers = [$this->named_resolvers->get($resolver_name), new Request_Attribute_Value_Resolver(), new Default_Value_Resolver()];
                }
            }
            $value_resolver_exceptions = [];
            foreach ($argument_value_resolvers as $name => $resolver) {
                if (isset($disabled_resolvers[\is_int($name) ? $resolver::class : $name])) {
                    continue;
                }
                try {
                    $count = 0;
                    foreach ($resolver->resolve($request, $metadata) as $argument) {
                        ++$count;
                        $arguments[] = $argument;
                    }
                } catch (Near_Miss_Value_Resolver_Exception $e) {
                    $value_resolver_exceptions[] = $e;
                }
                if (1 < $count && !$metadata->is_variadic()) {
                    throw new \InvalidArgumentException(\sprintf('"%s::resolve()" must yield at most one value for non-variadic arguments.', get_debug_type($resolver)));
                }
                if ($count) {
                    // continue to the next controller argument
                    continue 2;
                }
            }
            $reasons = array_map(static fn(Near_Miss_Value_Resolver_Exception $e): string => $e->get_message(), $value_resolver_exceptions);
            if (!$reasons) {
                $reasons[] = 'Either the argument is nullable and no null value has been provided, no default value has been provided or there is a non-optional argument after this one.';
            }
            $reason_counter = 1;
            if (\count($reasons) > 1) {
                foreach ($reasons as $i => $reason) {
                    $reasons[$i] = $reason_counter . ') ' . $reason;
                    ++$reason_counter;
                }
            }
            throw new \RuntimeException(\sprintf('Controller "%s" requires the "$%s" argument that could not be resolved. ' . ($reason_counter > 1 ? 'Possible reasons: ' : '') . '%s', $metadata->get_controller_name(), $metadata->get_name(), implode(' ', $reasons)));
        }
        return $arguments;
    }
    /**
     * @return iterable<int, ValueResolverInterface>
     */
    public static function get_default_argument_value_resolvers(): iterable
    {
        return [new Request_Attribute_Value_Resolver(), new Request_Value_Resolver(), new Session_Value_Resolver(), new Default_Value_Resolver(), new Variadic_Value_Resolver()];
    }
}
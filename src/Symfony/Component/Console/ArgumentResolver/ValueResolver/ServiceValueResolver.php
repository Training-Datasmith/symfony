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
namespace Symfony\Component\Console\Argument_Resolver\Value_Resolver;

use Psr\Container\Container_Interface;
use Symfony\Component\Console\Argument_Resolver\Exception\Near_Miss_Value_Resolver_Exception;
use Symfony\Component\Console\Attribute\Reflection\Reflection_Member;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
/**
 * Yields a service from a service locator keyed by command and argument name.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final readonly class Service_Value_Resolver implements Value_Resolver_Interface
{
    public function __construct(private Container_Interface $container)
    {
    }
    public function resolve(string $argument_name, Input_Interface $input, Reflection_Member $member): iterable
    {
        $command = $input->get_first_argument();
        if ($command && $this->container->has($command)) {
            $locator = $this->container->get($command);
            if ($locator instanceof Container_Interface && $locator->has($argument_name)) {
                try {
                    return [$locator->get($argument_name)];
                } catch (RuntimeException|\Throwable $e) {
                    $what = \sprintf('argument $%s', $argument_name);
                    $message = str_replace(\sprintf('service "%s"', $argument_name), $what, $e->get_message());
                    $what .= \sprintf(' of command "%s"', $command);
                    $message = preg_replace('/service "\.service_locator\.[^"]++"/', $what, $message);
                    if ($e->get_message() === $message) {
                        $message = \sprintf('Cannot resolve %s: %s', $what, $message);
                    }
                    throw new Near_Miss_Value_Resolver_Exception($message, $e->get_code(), $e);
                }
            }
        }
        $type = $member->get_type();
        if (!$type instanceof \ReflectionNamedType || $type->is_builtin()) {
            return [];
        }
        $type_name = $type->get_name();
        if (!$this->container->has($type_name)) {
            return [];
        }
        try {
            $service = $this->container->get($type_name);
            if (!$service instanceof $type_name) {
                throw new Near_Miss_Value_Resolver_Exception(\sprintf('Service "%s" exists in the container but is not an instance of "%s".', $type_name, $type_name));
            }
            return [$service];
        } catch (\Throwable $e) {
            throw new Near_Miss_Value_Resolver_Exception(\sprintf('Cannot resolve parameter "$%s" of type "%s": %s', $argument_name, $type_name, $e->get_message()), previous: $e);
        }
    }
}
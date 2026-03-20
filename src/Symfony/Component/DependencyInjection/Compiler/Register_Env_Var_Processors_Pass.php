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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Env_Var_Processor;
use Symfony\Component\Dependency_Injection\Env_Var_Processor_Interface;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Env_Placeholder_Parameter_Bag;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Creates the container.env_var_processors_locator service.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Register_Env_Var_Processors_Pass implements Compiler_Pass_Interface
{
    private const ALLOWED_TYPES = ['array', 'bool', 'float', 'int', 'string', \Backed_Enum::class];
    public function process(Container_Builder $container): void
    {
        $bag = $container->get_parameter_bag();
        $types = [];
        $processors = [];
        foreach ($container->find_tagged_service_ids('container.env_var_processor') as $id => $tags) {
            if (!$r = $container->get_reflection_class($class = $container->get_definition($id)->get_class())) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            if (!$r->is_subclass_of(Env_Var_Processor_Interface::class)) {
                throw new InvalidArgumentException(\sprintf('Service "%s" must implement interface "%s".', $id, Env_Var_Processor_Interface::class));
            }
            foreach ($class::get_provided_types() as $prefix => $type) {
                $processors[$prefix] = new Reference($id);
                $types[$prefix] = self::validate_provided_types($type, $class);
            }
        }
        if ($bag instanceof Env_Placeholder_Parameter_Bag) {
            foreach (Env_Var_Processor::get_provided_types() as $prefix => $type) {
                if (!isset($types[$prefix])) {
                    $types[$prefix] = self::validate_provided_types($type, Env_Var_Processor::class);
                }
            }
            $bag->set_provided_types($types);
        }
        if ($processors) {
            $container->set_alias('container.env_var_processors_locator', (string) Service_Locator_Tag_Pass::register($container, $processors))->set_public(true);
        }
    }
    private static function validate_provided_types(string $types, string $class): array
    {
        $types = explode('|', $types);
        foreach ($types as $type) {
            if (!\in_array($type, self::ALLOWED_TYPES, true)) {
                throw new InvalidArgumentException(\sprintf('Invalid type "%s" returned by "%s::getProvidedTypes()", expected one of "%s".', $type, $class, implode('", "', self::ALLOWED_TYPES)));
            }
        }
        return $types;
    }
}
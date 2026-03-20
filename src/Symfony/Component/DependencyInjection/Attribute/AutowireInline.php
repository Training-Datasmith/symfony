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
namespace Symfony\Component\Dependency_Injection\Attribute;

use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Symfony\Component\Dependency_Injection\Loader\Yaml_File_Loader;
/**
 * Allows inline service definition for an argument.
 *
 * Using this attribute on a class autowires a new instance
 * which is not shared between different services.
 *
 * $class a FQCN, or an array to define a factory.
 * Use the "@" prefix to reference a service.
 *
 * @author Ismail Özgün Turan <oezguen.turan@dadadev.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Autowire_Inline extends Autowire
{
    public function __construct(string|array|null $class = null, array $arguments = [], array $calls = [], array $properties = [], ?string $parent = null, bool|string $lazy = false)
    {
        if (null === $class && null === $parent) {
            throw new LogicException('#[AutowireInline] attribute should declare either $class or $parent.');
        }
        parent::__construct([\is_array($class) ? 'factory' : 'class' => $class, 'arguments' => $arguments, 'calls' => $calls, 'properties' => $properties, 'parent' => $parent], lazy: $lazy);
    }
    public function build_definition(mixed $value, ?string $type, \ReflectionParameter $parameter): Definition
    {
        static $parse_definition;
        static $yaml_loader;
        $parse_definition ??= new \ReflectionMethod(Yaml_File_Loader::class, 'parseDefinition');
        $yaml_loader ??= $parse_definition->get_declaring_class()->new_instance_without_constructor();
        if (isset($value['factory'])) {
            $value['class'] = $type;
            $value['factory'][0] ??= $type;
            $value['factory'][1] ??= '__invoke';
        }
        $class = $parameter->get_declaring_class();
        return $parse_definition->invoke($yaml_loader, $class->name, $value, $class->get_file_name(), ['autowire' => true], true);
    }
}
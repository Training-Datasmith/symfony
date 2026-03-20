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
namespace Symfony\Bundle\Twig_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Child_Definition;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\LogicException;
use Twig\Attribute\As_Twig_Filter;
use Twig\Attribute\As_Twig_Function;
use Twig\Attribute\As_Twig_Test;
use Twig\Extension\Abstract_Extension;
use Twig\Extension\Attribute_Extension;
use Twig\Extension\Extension_Interface;
/**
 * Register an instance of AttributeExtension for each service using the
 * PHP attributes to declare Twig callables.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * @internal
 */
final class Attribute_Extension_Pass implements Compiler_Pass_Interface
{
    private const TAG = 'twig.attribute_extension';
    public static function autoconfigure_from_attribute(Child_Definition $definition, As_Twig_Filter|As_Twig_Function|As_Twig_Test $attribute, \ReflectionMethod $reflector): void
    {
        $class = $reflector->get_declaring_class();
        if ($class->implements_interface(Extension_Interface::class)) {
            if ($class->is_subclass_of(Abstract_Extension::class)) {
                throw new LogicException(\sprintf('The class "%s" cannot extend "%s" and use the "#[%s]" attribute on method "%s()", choose one or the other.', $class->name, Abstract_Extension::class, $attribute::class, $reflector->name));
            }
            throw new LogicException(\sprintf('The class "%s" cannot implement "%s" and use the "#[%s]" attribute on method "%s()", choose one or the other.', $class->name, Extension_Interface::class, $attribute::class, $reflector->name));
        }
        $definition->add_tag(self::TAG);
        // The service must be tagged as a runtime to call non-static methods
        if (!$reflector->is_static()) {
            $definition->add_tag('twig.runtime');
        }
    }
    public function process(Container_Builder $container): void
    {
        foreach ($container->find_tagged_service_ids(self::TAG, true) as $id => $tags) {
            $container->register('.twig.extension.' . $id, Attribute_Extension::class)->set_arguments([$container->get_definition($id)->get_class()])->add_tag('twig.extension');
        }
    }
}
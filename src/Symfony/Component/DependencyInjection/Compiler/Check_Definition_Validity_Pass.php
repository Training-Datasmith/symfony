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
use Symfony\Component\Dependency_Injection\Exception\Env_Parameter_Exception;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Loader\File_Loader;
/**
 * This pass validates each definition individually only taking the information
 * into account which is contained in the definition itself.
 *
 * Later passes can rely on the following, and specifically do not need to
 * perform these checks themselves:
 *
 * - non synthetic, non abstract services always have a class set
 * - synthetic services are always public
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Check_Definition_Validity_Pass implements Compiler_Pass_Interface
{
    /**
     * Processes the ContainerBuilder to validate the Definition.
     *
     * @throws RuntimeException When the Definition is invalid
     */
    public function process(Container_Builder $container): void
    {
        foreach ($container->get_definitions() as $id => $definition) {
            if ($definition->has_errors()) {
                continue;
            }
            // synthetic service is public
            if ($definition->is_synthetic() && !$definition->is_public()) {
                throw new RuntimeException(\sprintf('A synthetic service ("%s") must be public.', $id));
            }
            // non-synthetic, non-abstract service has class
            if (!$definition->is_abstract() && !$definition->is_synthetic() && !$definition->get_class() && !$definition->has_tag('container.service_locator') && (!$definition->get_factory() || !preg_match(File_Loader::ANONYMOUS_ID_REGEXP, $id))) {
                if ($definition->get_factory()) {
                    throw new RuntimeException(\sprintf('Please add the class to service "%s" even if it is constructed by a factory since we might need to add method calls based on compile-time checks.', $id));
                }
                if (class_exists($id) || interface_exists($id, false)) {
                    if (str_starts_with($id, '\\') && 1 < substr_count($id, '\\')) {
                        throw new RuntimeException(\sprintf('The definition for "%s" has no class attribute, and appears to reference a class or interface. Please specify the class attribute explicitly or remove the leading backslash by renaming the service to "%s" to get rid of this error.', $id, substr($id, 1)));
                    }
                    throw new RuntimeException(\sprintf('The definition for "%s" has no class attribute, and appears to reference a class or interface in the global namespace. Leaving out the "class" attribute is only allowed for namespaced classes. Please specify the class attribute explicitly to get rid of this error.', $id));
                }
                throw new RuntimeException(\sprintf('The definition for "%s" has no class. If you intend to inject this service dynamically at runtime, please mark it as synthetic=true. If this is an abstract definition solely used by child definitions, please add abstract=true, otherwise specify a class to get rid of this error.', $id));
            }
            // tag attribute values must be scalars
            foreach ($definition->get_tags() as $name => $tags) {
                foreach ($tags as $attributes) {
                    $this->validate_attributes($id, $name, $attributes);
                }
            }
            if ($definition->is_public()) {
                $resolved_id = $container->resolve_env_placeholders($id, null, $used_envs);
                if (null !== $used_envs) {
                    throw new Env_Parameter_Exception([$resolved_id], null, 'A service name ("%s") cannot contain dynamic values.');
                }
            }
        }
        foreach ($container->get_aliases() as $id => $alias) {
            if ($alias->is_public()) {
                $resolved_id = $container->resolve_env_placeholders($id, null, $used_envs);
                if (null !== $used_envs) {
                    throw new Env_Parameter_Exception([$resolved_id], null, 'An alias name ("%s") cannot contain dynamic values.');
                }
            }
        }
    }
    private function validate_attributes(string $id, string $tag, array $attributes, array $path = []): void
    {
        foreach ($attributes as $name => $value) {
            if (\is_array($value)) {
                $this->validate_attributes($id, $tag, $value, [...$path, $name]);
            } elseif (!\is_scalar($value) && null !== $value) {
                $name = implode('.', [...$path, $name]);
                throw new RuntimeException(\sprintf('A "tags" attribute must be of a scalar-type for service "%s", tag "%s", attribute "%s".', $id, $tag, $name));
            }
        }
    }
}
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
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
final class Alias_Deprecated_Public_Services_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $aliases = [];
    public function process(Container_Builder $container): void
    {
        foreach ($container->find_tagged_service_ids('container.private') as $id => $tags) {
            if (null === $package = $tags[0]['package'] ?? null) {
                throw new InvalidArgumentException(\sprintf('The "package" attribute is mandatory for the "container.private" tag on the "%s" service.', $id));
            }
            if (null === $version = $tags[0]['version'] ?? null) {
                throw new InvalidArgumentException(\sprintf('The "version" attribute is mandatory for the "container.private" tag on the "%s" service.', $id));
            }
            $definition = $container->get_definition($id);
            if ($definition->is_private()) {
                continue;
            }
            $container->set_alias($id, $alias_id = '.container.private.' . $id)->set_public(true)->set_deprecated($package, $version, 'Accessing the "%alias_id%" service directly from the container is deprecated, use dependency injection instead.');
            $container->set_definition($alias_id, $definition);
            $this->aliases[$id] = $alias_id;
        }
        parent::process($container);
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Reference && isset($this->aliases[$id = (string) $value])) {
            return new Reference($this->aliases[$id], $value->get_invalid_behavior());
        }
        return parent::process_value($value, $is_root);
    }
}
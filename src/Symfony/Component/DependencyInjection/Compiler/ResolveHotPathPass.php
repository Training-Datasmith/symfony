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

use Symfony\Component\Dependency_Injection\Argument\Argument_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Propagate "container.hot_path" tags to referenced services.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Resolve_Hot_Path_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $resolved_ids = [];
    public function process(Container_Builder $container): void
    {
        try {
            parent::process($container);
            $container->get_definition('service_container')->clear_tag('container.hot_path');
        } finally {
            $this->resolved_ids = [];
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Argument_Interface) {
            return $value;
        }
        if ($value instanceof Definition && $is_root) {
            if ($value->is_deprecated()) {
                return $value->clear_tag('container.hot_path');
            }
            $this->resolved_ids[$this->current_id ?? ''] = true;
            if (!$value->has_tag('container.hot_path')) {
                return $value;
            }
        }
        if ($value instanceof Reference && Container_Builder::IGNORE_ON_UNINITIALIZED_REFERENCE !== $value->get_invalid_behavior() && $this->container->has_definition($id = (string) $value)) {
            $definition = $this->container->get_definition($id);
            if ($definition->is_deprecated() || $definition->has_tag('container.hot_path')) {
                return $value;
            }
            $definition->add_tag('container.hot_path');
            if (isset($this->resolved_ids[$id])) {
                parent::process_value($definition, false);
            }
            return $value;
        }
        return parent::process_value($value, $is_root);
    }
}
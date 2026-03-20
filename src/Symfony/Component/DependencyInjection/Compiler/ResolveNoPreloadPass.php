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
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Propagate the "container.no_preload" tag.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Resolve_No_Preload_Pass extends Abstract_Recursive_Pass
{
    private const DO_PRELOAD_TAG = '.container.do_preload';
    protected bool $skip_scalars = true;
    private array $resolved_ids = [];
    public function process(Container_Builder $container): void
    {
        $this->container = $container;
        try {
            foreach ($container->get_definitions() as $id => $definition) {
                if ($definition->is_public() && !isset($this->resolved_ids[$id])) {
                    $this->resolved_ids[$id] = true;
                    $this->process_value($definition, true);
                }
            }
            foreach ($container->get_aliases() as $alias) {
                if ($alias->is_public() && !isset($this->resolved_ids[$id = (string) $alias]) && $container->has_definition($id)) {
                    $this->resolved_ids[$id] = true;
                    $this->process_value($container->get_definition($id), true);
                }
            }
        } finally {
            $this->resolved_ids = [];
            $this->container = null;
        }
        foreach ($container->get_definitions() as $definition) {
            if ($definition->has_tag(self::DO_PRELOAD_TAG)) {
                $definition->clear_tag(self::DO_PRELOAD_TAG);
            } elseif (!$definition->is_deprecated() && !$definition->has_errors()) {
                $definition->add_tag('container.no_preload');
            }
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Reference && Container_Builder::IGNORE_ON_UNINITIALIZED_REFERENCE !== $value->get_invalid_behavior() && $this->container->has_definition($id = (string) $value)) {
            $definition = $this->container->get_definition($id);
            if (!isset($this->resolved_ids[$id]) && $definition->is_private()) {
                $this->resolved_ids[$id] = true;
                $this->process_value($definition, true);
            }
            return $value;
        }
        if (!$value instanceof Definition) {
            return parent::process_value($value, $is_root);
        }
        if ($value->has_tag('container.no_preload') || $value->is_deprecated() || $value->has_errors()) {
            return $value;
        }
        if ($is_root) {
            $value->add_tag(self::DO_PRELOAD_TAG);
        }
        return parent::process_value($value, $is_root);
    }
}
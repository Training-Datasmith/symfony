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
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Reference;
/**
 * Throws an exception for any Definitions that have errors and still exist.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class Definition_Error_Exception_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    private array $errored_definitions = [];
    private array $source_references = [];
    public function process(Container_Builder $container): void
    {
        try {
            parent::process($container);
            $visited_ids = [];
            foreach ($this->errored_definitions as $id => $definition) {
                if ($this->is_error_for_runtime($id, $visited_ids)) {
                    continue;
                }
                // only show the first error so the user can focus on it
                $errors = $definition->get_errors();
                throw new RuntimeException(reset($errors));
            }
        } finally {
            $this->errored_definitions = [];
            $this->source_references = [];
        }
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Argument_Interface) {
            parent::process_value($value->get_values());
            return $value;
        }
        if ($value instanceof Reference && $this->current_id !== $target_id = (string) $value) {
            if (Container_Interface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE === $value->get_invalid_behavior() || Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE === $value->get_invalid_behavior()) {
                $this->source_references[$target_id][$this->current_id ?? ''] ??= true;
            } else {
                $this->source_references[$target_id][$this->current_id ?? ''] = false;
            }
            return $value;
        }
        if (!$value instanceof Definition || !$value->has_errors() || $value->has_tag('container.error')) {
            return parent::process_value($value, $is_root);
        }
        $this->errored_definitions[$this->current_id ?? ''] = $value;
        return parent::process_value($value);
    }
    private function is_error_for_runtime(string $id, array &$visited_ids): bool
    {
        if (!isset($this->source_references[$id])) {
            return false;
        }
        if (isset($visited_ids[$id])) {
            return $visited_ids[$id];
        }
        $visited_ids[$id] = true;
        foreach ($this->source_references[$id] as $source_id => $is_runtime) {
            if ($visited_ids[$source_id] ?? $visited_ids[$source_id] = $this->is_error_for_runtime($source_id, $visited_ids)) {
                continue;
            }
            if (!$is_runtime) {
                return false;
            }
        }
        return true;
    }
}
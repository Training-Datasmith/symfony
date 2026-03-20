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
use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Typed_Reference;
/**
 * Emulates the invalid behavior if the reference is not found within the
 * container.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Resolve_Invalid_References_Pass implements Compiler_Pass_Interface
{
    private Container_Builder $container;
    private RuntimeException $signaling_exception;
    private readonly string $current_id;
    /**
     * Process the ContainerBuilder to resolve invalid references.
     */
    public function process(Container_Builder $container): void
    {
        $this->container = $container;
        $this->signaling_exception = new RuntimeException('Invalid reference.');
        try {
            foreach ($container->get_definitions() as $this->current_id => $definition) {
                $this->process_value($definition);
            }
        } finally {
            unset($this->container, $this->signaling_exception);
        }
    }
    /**
     * Processes arguments to determine invalid references.
     *
     * @throws RuntimeException When an invalid reference is found
     */
    private function process_value(mixed $value, int $root_level = 0, int $level = 0): mixed
    {
        if ($value instanceof Service_Closure_Argument) {
            $value->set_values($this->process_value($value->get_values(), 1, 1));
        } elseif ($value instanceof Argument_Interface) {
            $value->set_values($this->process_value($value->get_values(), $root_level, 1 + $level));
        } elseif ($value instanceof Definition) {
            if ($value->is_synthetic() || $value->is_abstract() || $value->has_tag('container.excluded')) {
                return $value;
            }
            $value->set_arguments($this->process_value($value->get_arguments(), 0));
            $value->set_properties($this->process_value($value->get_properties(), 1));
            $value->set_method_calls($this->process_value($value->get_method_calls(), 2));
        } elseif (\is_array($value)) {
            $i = 0;
            foreach ($value as $k => $v) {
                try {
                    if (false !== $i && $k !== $i++) {
                        $i = false;
                    }
                    if ($v !== $processed_value = $this->process_value($v, $root_level, 1 + $level)) {
                        $value[$k] = $processed_value;
                    }
                } catch (RuntimeException $e) {
                    if ($root_level < $level || $root_level && !$level) {
                        unset($value[$k]);
                    } elseif ($root_level) {
                        throw $e;
                    } else {
                        $value[$k] = null;
                    }
                }
            }
            // Ensure numerically indexed arguments have sequential numeric keys.
            if (false !== $i) {
                $value = array_values($value);
            }
        } elseif ($value instanceof Reference) {
            if ($this->container->has_definition($id = (string) $value) ? !$this->container->get_definition($id)->has_tag('container.excluded') : $this->container->has_alias($id)) {
                return $value;
            }
            $current_definition = $this->container->get_definition($this->current_id);
            // resolve decorated service behavior depending on decorator service
            if ($current_definition->inner_service_id === $id && Container_Interface::NULL_ON_INVALID_REFERENCE === $current_definition->decoration_on_invalid) {
                return null;
            }
            $invalid_behavior = $value->get_invalid_behavior();
            if (Container_Interface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE === $invalid_behavior && $value instanceof Typed_Reference && !$this->container->has($id)) {
                $e = new Service_Not_Found_Exception($id, $this->current_id);
                // since the error message varies by $id and $this->currentId, so should the id of the dummy errored definition
                $this->container->register($id = \sprintf('.errored.%s.%s', $this->current_id, $id), $value->get_type())->add_error($e->get_message());
                return new Typed_Reference($id, $value->get_type(), $value->get_invalid_behavior());
            }
            // resolve invalid behavior
            if (Container_Interface::NULL_ON_INVALID_REFERENCE === $invalid_behavior) {
                $value = null;
            } elseif (Container_Interface::IGNORE_ON_INVALID_REFERENCE === $invalid_behavior) {
                if (0 < $level || $root_level) {
                    throw $this->signaling_exception;
                }
                $value = null;
            }
        }
        return $value;
    }
}
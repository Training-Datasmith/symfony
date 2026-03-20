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
use Symfony\Component\Dependency_Injection\Exception\Parameter_Not_Found_Exception;
use Symfony\Component\Dependency_Injection\Parameter_Bag\Parameter_Bag_Interface;
/**
 * Resolves all parameter placeholders "%somevalue%" to their real values.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Resolve_Parameter_Place_Holders_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = false;
    private Parameter_Bag_Interface $bag;
    public function __construct(private readonly bool $resolve_arrays = true, private readonly bool $throw_on_resolve_exception = true)
    {
    }
    /**
     * @throws ParameterNotFoundException
     */
    public function process(Container_Builder $container): void
    {
        $this->bag = $container->get_parameter_bag();
        try {
            parent::process($container);
            $aliases = [];
            foreach ($container->get_aliases() as $name => $target) {
                $this->current_id = $name;
                $aliases[$this->bag->resolve_value($name)] = $target;
            }
            $container->set_aliases($aliases);
        } catch (Parameter_Not_Found_Exception $e) {
            $e->set_source_id($this->current_id);
            throw $e;
        }
        $this->bag->resolve();
        unset($this->bag);
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (\is_string($value)) {
            try {
                $v = $this->bag->resolve_value($value);
            } catch (Parameter_Not_Found_Exception $e) {
                if ($this->throw_on_resolve_exception) {
                    throw $e;
                }
                $v = null;
                $this->container->get_definition($this->current_id)->add_error($e->get_message());
            }
            return $this->resolve_arrays || !$v || !\is_array($v) ? $v : $value;
        }
        if ($value instanceof Definition) {
            $value->set_bindings($this->process_value($value->get_bindings()));
            $changes = $value->get_changes();
            if (isset($changes['class'])) {
                $value->set_class($this->bag->resolve_value($value->get_class()));
            }
            if (isset($changes['file'])) {
                $value->set_file($this->bag->resolve_value($value->get_file()));
            }
            $tags = $value->get_tags();
            if (isset($tags['proxy'])) {
                $tags['proxy'] = $this->bag->resolve_value($tags['proxy']);
                $value->set_tags($tags);
            }
        }
        $value = parent::process_value($value, $is_root);
        if ($value && \is_array($value)) {
            return array_combine($this->bag->resolve_value(array_keys($value)), $value);
        }
        return $value;
    }
}
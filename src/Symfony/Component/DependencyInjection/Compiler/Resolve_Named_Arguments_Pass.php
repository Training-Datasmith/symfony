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

use Symfony\Component\Dependency_Injection\Argument\Abstract_Argument;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Var_Exporter\Proxy_Helper;
/**
 * Resolves named arguments to their corresponding numeric index.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Resolve_Named_Arguments_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if ($value instanceof Abstract_Argument && $value->get_text() . '.' === $value->get_text_with_context()) {
            $value->set_context(\sprintf('A value found in service "%s"', $this->current_id));
        }
        if (!$value instanceof Definition) {
            return parent::process_value($value, $is_root);
        }
        $calls = $value->get_method_calls();
        $calls[] = ['__construct', $value->get_arguments()];
        foreach ($calls as $i => $call) {
            [$method, $arguments] = $call;
            $parameters = null;
            $resolved_keys = [];
            $resolved_arguments = [];
            foreach ($arguments as $key => $argument) {
                if ($argument instanceof Abstract_Argument && $argument->get_text() . '.' === $argument->get_text_with_context()) {
                    $argument->set_context(\sprintf('Argument ' . (\is_int($key) ? 1 + $key : '"%3$s"') . ' of ' . ('__construct' === $method ? 'service "%s"' : 'method call "%s::%s()"'), $this->current_id, $method, $key));
                }
                if (\is_int($key)) {
                    $resolved_keys[$key] = $key;
                    $resolved_arguments[$key] = $argument;
                    continue;
                }
                if (null === $parameters) {
                    $r = $this->get_reflection_method($value, $method);
                    $class = $r instanceof \ReflectionMethod ? $r->class : $this->current_id;
                    $method = $r->get_name();
                    $parameters = $r->get_parameters();
                }
                if (isset($key[0]) && '$' !== $key[0] && !class_exists($key) && !interface_exists($key, false)) {
                    throw new InvalidArgumentException(\sprintf('Invalid service "%s": did you forget to add the "$" prefix to argument "%s"?', $this->current_id, $key));
                }
                if (isset($key[0]) && '$' === $key[0]) {
                    foreach ($parameters as $j => $p) {
                        if ($key === '$' . $p->name) {
                            if ($p->is_variadic() && \is_array($argument)) {
                                foreach ($argument as $variadic_argument) {
                                    $resolved_keys[$j] = $j;
                                    $resolved_arguments[$j++] = $variadic_argument;
                                }
                            } else {
                                $resolved_keys[$j] = $p->name;
                                $resolved_arguments[$j] = $argument;
                            }
                            continue 2;
                        }
                    }
                    throw new InvalidArgumentException(\sprintf('Invalid service "%s": method "%s()" has no argument named "%s". Check your service definition.', $this->current_id, $class !== $this->current_id ? $class . '::' . $method : $method, $key));
                }
                if (null !== $argument && !$argument instanceof Reference && !$argument instanceof Definition) {
                    throw new InvalidArgumentException(\sprintf('Invalid service "%s": the value of argument "%s" of method "%s()" must be null, an instance of "%s" or an instance of "%s", "%s" given.', $this->current_id, $key, $class !== $this->current_id ? $class . '::' . $method : $method, Reference::class, Definition::class, get_debug_type($argument)));
                }
                $type_found = false;
                foreach ($parameters as $j => $p) {
                    if (!\array_key_exists($j, $resolved_arguments) && Proxy_Helper::export_type($p, true) === $key) {
                        $resolved_keys[$j] = $p->name;
                        $resolved_arguments[$j] = $argument;
                        $type_found = true;
                    }
                }
                if (!$type_found) {
                    throw new InvalidArgumentException(\sprintf('Invalid service "%s": method "%s()" has no argument type-hinted as "%s". Check your service definition.', $this->current_id, $class !== $this->current_id ? $class . '::' . $method : $method, $key));
                }
            }
            if ($resolved_arguments !== $call[1]) {
                ksort($resolved_arguments);
                if (!$value->is_autowired() && !array_is_list($resolved_arguments)) {
                    ksort($resolved_keys);
                    $resolved_arguments = array_combine($resolved_keys, $resolved_arguments);
                }
                $calls[$i][1] = $resolved_arguments;
            }
        }
        [, $arguments] = array_pop($calls);
        if ($arguments !== $value->get_arguments()) {
            $value->set_arguments($arguments);
        }
        if ($calls !== $value->get_method_calls()) {
            $value->set_method_calls($calls);
        }
        foreach ($value->get_properties() as $key => $argument) {
            if ($argument instanceof Abstract_Argument && $argument->get_text() . '.' === $argument->get_text_with_context()) {
                $argument->set_context(\sprintf('Property "%s" of service "%s"', $key, $this->current_id));
            }
        }
        return parent::process_value($value, $is_root);
    }
}
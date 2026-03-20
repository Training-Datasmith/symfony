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
namespace Symfony\Component\Dependency_Injection\Argument;

use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Var_Exporter\Proxy_Helper;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Lazy_Closure
{
    public readonly object $service;
    public function __construct(private \Closure $initializer)
    {
        unset($this->service);
    }
    public function __get(mixed $name): mixed
    {
        if ('service' !== $name) {
            throw new InvalidArgumentException(\sprintf('Cannot read property "%s" from a lazy closure.', $name));
        }
        if (isset($this->initializer)) {
            if (\is_string($service = ($this->initializer)())) {
                $service = (new \ReflectionClass($service))->new_instance_without_constructor();
            }
            $this->service = $service;
            unset($this->initializer);
        }
        return $this->service;
    }
    public static function get_code(string $initializer, array $callable, string $class, Container_Builder $container, ?string $id): string
    {
        $method = $callable[1];
        if ($as_closure = 'Closure' === $class) {
            $class = ($callable[0] instanceof Reference ? $container->find_definition($callable[0]) : $callable[0])->get_class();
        }
        $r = $container->get_reflection_class($class);
        if (null !== $id) {
            $id = \sprintf(' for service "%s"', $id);
        }
        if (!$as_closure) {
            $id = str_replace('%', '%%', (string) $id);
            if (!$r || !$r->is_interface()) {
                throw new RuntimeException(\sprintf("Cannot create adapter{$id} because \"%s\" is not an interface.", $class));
            }
            if (1 !== \count($method = $r->get_methods())) {
                throw new RuntimeException(\sprintf("Cannot create adapter{$id} because interface \"%s\" doesn't have exactly one method.", $class));
            }
            $method = $method[0]->name;
        } elseif (!$r || !$r->has_method($method)) {
            throw new RuntimeException("Cannot create lazy closure{$id} because its corresponding callable is invalid.");
        }
        $method_reflector = $r->get_method($method);
        $code = Proxy_Helper::export_signature($method_reflector, true, $args);
        if ($as_closure) {
            $code = ' { ' . preg_replace('/: static$/', ': \\' . $r->name, $code);
        } else {
            $code = ' implements \\' . $r->name . ' { ' . $code;
        }
        $code = 'new class(' . $initializer . ') extends \\' . self::class . $code . ' { ' . ($method_reflector->has_return_type() && 'void' === (string) $method_reflector->get_return_type() ? '' : 'return ') . '$this->service->' . $callable[1] . '(' . $args . '); } ' . '}';
        return $as_closure ? '(' . $code . ')->' . $method . '(...)' : $code;
    }
}
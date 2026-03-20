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
namespace Symfony\Component\Http_Kernel\Event;

use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
/**
 * Allows filtering of a controller callable.
 *
 * You can call getController() to retrieve the current controller. With
 * setController() you can set a new controller that is used in the processing
 * of the request.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class Controller_Event extends Kernel_Event
{
    private string|array|object $controller;
    private \Reflection_Function_Abstract $controller_reflector;
    public function __construct(Http_Kernel_Interface $kernel, callable $controller, Request $request, ?int $request_type)
    {
        parent::__construct($kernel, $request, $request_type);
        $this->set_controller($controller);
    }
    public function get_controller(): callable
    {
        return $this->controller;
    }
    public function get_controller_reflector(): \Reflection_Function_Abstract
    {
        return $this->controller_reflector;
    }
    /**
     * @param list<object>|null $attributes
     */
    public function set_controller(callable $controller, ?array $attributes = null): void
    {
        if (null !== $attributes) {
            if (!array_is_list($flatten_attributes = $attributes)) {
                trigger_deprecation('symfony/http-kernel', '8.1', 'Passing an array of attributes grouped by class name to "%s()" is deprecated. Pass a flat list of attributes instead.', __METHOD__);
                $flatten_attributes = [];
                foreach ($attributes as $attributes) {
                    foreach (\is_array($attributes) ? $attributes : [$attributes] as $attribute) {
                        $flatten_attributes[] = $attribute;
                    }
                }
            }
            $this->get_request()->attributes->set('_controller_attributes', $flatten_attributes);
        }
        if (isset($this->controller) && ($controller instanceof \Closure ? $controller == $this->controller : $controller === $this->controller)) {
            $this->controller = $controller;
            return;
        }
        if (null === $attributes) {
            $this->get_request()->attributes->remove('_controller_attributes');
        }
        $this->controller_reflector = match (true) {
            \is_array($controller) && method_exists(...$controller) => new \ReflectionMethod(...$controller),
            \is_string($controller) && str_contains($controller, '::') => new \ReflectionMethod(...explode('::', $controller, 2)),
            default => new \ReflectionFunction($controller(...)),
        };
        $this->controller = $controller;
    }
    /**
     * @template T of object
     *
     * @param class-string<T>|'*'|null $className
     *
     * @return ($className is null ? array<class-string, list<object>> : ($className is '*' ? list<object> : list<T>))
     */
    public function get_attributes(?string $class_name = null): array
    {
        if (null === $attributes = $this->get_request()->attributes->get('_controller_attributes')) {
            $class = match (true) {
                \is_array($this->controller) && method_exists(...$this->controller) => new \ReflectionClass($this->controller[0]),
                \is_string($this->controller) && false !== $i = strpos($this->controller, '::') => new \ReflectionClass(substr($this->controller, 0, $i)),
                $this->controller_reflector instanceof \ReflectionFunction => $this->controller_reflector->is_anonymous() ? null : $this->controller_reflector->get_closure_called_class(),
            };
            $attributes = [];
            foreach (array_merge($class?->get_attributes() ?? [], $this->controller_reflector->get_attributes()) as $attribute) {
                if (class_exists($attribute->get_name())) {
                    $attributes[] = $attribute->new_instance();
                }
            }
            $this->get_request()->attributes->set('_controller_attributes', $attributes);
        }
        if ('*' === $class_name) {
            return $attributes;
        }
        if (null !== $class_name) {
            return array_values(array_filter($attributes, static fn($attr): bool => $attr instanceof $class_name));
        }
        $grouped = [];
        foreach ($attributes as $attribute) {
            $grouped[$attribute::class][] = $attribute;
        }
        return $grouped;
    }
    public function evaluate(mixed $value, ?Expression_Language $expression_language, array $args = []): mixed
    {
        if (!$value instanceof \Closure && !$value instanceof Expression) {
            return $value;
        }
        $controller = $this->get_controller();
        $controller = match (true) {
            \is_object($controller) && !$controller instanceof \Closure => $controller,
            \is_array($controller) && \is_object($controller[0]) => $controller[0],
            default => null,
        };
        if ($value instanceof \Closure) {
            return $value($args, $this->get_request(), $controller);
        }
        if (!$expression_language) {
            throw new \LogicException('Cannot evaluate Expression for controllers since no ExpressionLanguage service was configured.');
        }
        return $expression_language->evaluate($value, ['request' => $this->get_request(), 'args' => $args, 'this' => $controller]);
    }
}
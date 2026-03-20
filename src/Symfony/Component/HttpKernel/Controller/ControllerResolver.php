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
namespace Symfony\Component\Http_Kernel\Controller;

use Psr\Log\Logger_Interface;
use Symfony\Component\Http_Foundation\Exception\Bad_Request_Exception;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Attribute\As_Controller;
/**
 * This implementation uses the '_controller' request attribute to determine
 * the controller to execute.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class Controller_Resolver implements Controller_Resolver_Interface
{
    private array $allowed_controller_types = [];
    private array $allowed_controller_attributes = [As_Controller::class => As_Controller::class];
    public function __construct(private readonly ?Logger_Interface $logger = null)
    {
    }
    /**
     * @param array<class-string> $types
     * @param array<class-string> $attributes
     */
    public function allow_controllers(array $types = [], array $attributes = []): void
    {
        foreach ($types as $type) {
            $this->allowed_controller_types[$type] = $type;
        }
        foreach ($attributes as $attribute) {
            $this->allowed_controller_attributes[$attribute] = $attribute;
        }
    }
    /**
     * @throws BadRequestException when the request has attribute "_check_controller_is_allowed" set to true and the controller is not allowed
     */
    public function get_controller(Request $request): callable|false
    {
        if (!$controller = $request->attributes->get('_controller')) {
            $this->logger?->warning('Unable to look for the controller as the "_controller" parameter is missing.');
            return false;
        }
        if (\is_array($controller)) {
            if (isset($controller[0]) && \is_string($controller[0]) && isset($controller[1])) {
                try {
                    $controller[0] = $this->instantiate_controller($controller[0]);
                } catch (\Error|\LogicException $e) {
                    if (\is_callable($controller)) {
                        return $this->check_controller($request, $controller);
                    }
                    throw $e;
                }
            }
            if (!\is_callable($controller)) {
                throw new \InvalidArgumentException(\sprintf('The controller for URI "%s" is not callable: ', $request->get_path_info()) . $this->get_controller_error($controller));
            }
            return $this->check_controller($request, $controller);
        }
        if (\is_object($controller)) {
            if (!\is_callable($controller)) {
                throw new \InvalidArgumentException(\sprintf('The controller for URI "%s" is not callable: ', $request->get_path_info()) . $this->get_controller_error($controller));
            }
            return $this->check_controller($request, $controller);
        }
        if (\function_exists($controller)) {
            return $this->check_controller($request, $controller);
        }
        try {
            $callable = $this->create_controller($controller);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(\sprintf('The controller for URI "%s" is not callable: ', $request->get_path_info()) . $e->get_message(), 0, $e);
        }
        if (!\is_callable($callable)) {
            throw new \InvalidArgumentException(\sprintf('The controller for URI "%s" is not callable: ', $request->get_path_info()) . $this->get_controller_error($callable));
        }
        return $this->check_controller($request, $callable);
    }
    /**
     * Returns a callable for the given controller.
     *
     * @throws \InvalidArgumentException When the controller cannot be created
     */
    protected function create_controller(string $controller): callable
    {
        if (!str_contains($controller, '::')) {
            $controller = $this->instantiate_controller($controller);
            if (!\is_callable($controller)) {
                throw new \InvalidArgumentException($this->get_controller_error($controller));
            }
            return $controller;
        }
        [$class, $method] = explode('::', $controller, 2);
        try {
            $controller = [$this->instantiate_controller($class), $method];
        } catch (\Error|\LogicException $e) {
            try {
                if ((new \ReflectionMethod($class, $method))->is_static()) {
                    return $class . '::' . $method;
                }
            } catch (\Reflection_Exception) {
                throw $e;
            }
            throw $e;
        }
        if (!\is_callable($controller)) {
            throw new \InvalidArgumentException($this->get_controller_error($controller));
        }
        return $controller;
    }
    /**
     * Returns an instantiated controller.
     */
    protected function instantiate_controller(string $class): object
    {
        return new $class();
    }
    private function get_controller_error(mixed $callable): string
    {
        if (\is_string($callable)) {
            if (str_contains($callable, '::')) {
                $callable = explode('::', $callable, 2);
            } else {
                return \sprintf('Function "%s" does not exist.', $callable);
            }
        }
        if (\is_object($callable)) {
            $available_methods = $this->get_class_methods_without_magic_methods($callable);
            $alternative_msg = $available_methods ? \sprintf(' or use one of the available methods: "%s"', implode('", "', $available_methods)) : '';
            return \sprintf('Controller class "%s" cannot be called without a method name. You need to implement "__invoke"%s.', get_debug_type($callable), $alternative_msg);
        }
        if (!\is_array($callable)) {
            return \sprintf('Invalid type for controller given, expected string, array or object, got "%s".', get_debug_type($callable));
        }
        if (!isset($callable[0]) || !isset($callable[1]) || 2 !== \count($callable)) {
            return 'Invalid array callable, expected [controller, method].';
        }
        [$controller, $method] = $callable;
        if (\is_string($controller) && !class_exists($controller)) {
            return \sprintf('Class "%s" does not exist.', $controller);
        }
        $class_name = \is_object($controller) ? get_debug_type($controller) : $controller;
        if (method_exists($controller, $method)) {
            return \sprintf('Method "%s" on class "%s" should be public and non-abstract.', $method, $class_name);
        }
        $collection = $this->get_class_methods_without_magic_methods($controller);
        $alternatives = [];
        foreach ($collection as $item) {
            $lev = levenshtein($method, $item);
            if ($lev <= \strlen((string) $method) / 3 || str_contains((string) $item, (string) $method)) {
                $alternatives[] = $item;
            }
        }
        asort($alternatives);
        $message = \sprintf('Expected method "%s" on class "%s"', $method, $class_name);
        if (\count($alternatives) > 0) {
            $message .= \sprintf(', did you mean "%s"?', implode('", "', $alternatives));
        } else {
            $message .= \sprintf('. Available methods: "%s".', implode('", "', $collection));
        }
        return $message;
    }
    private function get_class_methods_without_magic_methods($class_or_object): array
    {
        $methods = get_class_methods($class_or_object);
        return array_filter($methods, static fn(string $method): bool => !str_starts_with($method, '__'));
    }
    private function check_controller(Request $request, callable $controller): callable
    {
        if (!$request->attributes->get('_check_controller_is_allowed', false)) {
            return $controller;
        }
        $r = null;
        if (\is_array($controller)) {
            [$class, $name] = $controller;
            $name = (\is_string($class) ? $class : $class::class) . '::' . $name;
        } elseif (\is_object($controller) && !$controller instanceof \Closure) {
            $class = $controller;
            $name = $class::class . '::__invoke';
        } else {
            $r = new \ReflectionFunction($controller);
            $name = $r->name;
            if ($r->is_anonymous()) {
                $name = $class = \Closure::class;
            } elseif ($class = $r->get_closure_called_class()) {
                $class = $class->name;
                $name = $class . '::' . $name;
            }
        }
        if ($class) {
            foreach ($this->allowed_controller_types as $type) {
                if (is_a($class, $type, true)) {
                    return $controller;
                }
            }
        }
        $r ??= new \ReflectionClass($class);
        foreach ($r->get_attributes() as $attribute) {
            if (isset($this->allowed_controller_attributes[$attribute->get_name()])) {
                return $controller;
            }
        }
        if (str_contains($name, '@anonymous')) {
            $name = preg_replace_callback('/[a-zA-Z_\x7f-\xff][\\\\a-zA-Z0-9_\x7f-\xff]*+@anonymous\x00.*?\.php(?:0x?|:[0-9]++\$)?[0-9a-fA-F]++/', static fn($m): string => class_exists($m[0], false) ? ((get_parent_class($m[0]) ?: key(class_implements($m[0]))) ?: 'class') . '@anonymous' : $m[0], $name);
        }
        throw new Bad_Request_Exception(\sprintf('Callable "%s()" is not allowed as a controller. Did you miss tagging it with "#[AsController]" or registering its type with "%s::allowControllers()"?', $name, self::class));
    }
}
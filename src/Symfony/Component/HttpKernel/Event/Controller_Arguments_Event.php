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
 * Allows filtering of controller arguments.
 *
 * You can call getController() to retrieve the controller and getArguments
 * to retrieve the current arguments. With setArguments() you can replace
 * arguments that are used to call the controller.
 *
 * Arguments set in the event must be compatible with the signature of the
 * controller.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
final class Controller_Arguments_Event extends Kernel_Event
{
    private readonly Controller_Event $controller_event;
    private array $named_arguments;
    public function __construct(Http_Kernel_Interface $kernel, callable|Controller_Event $controller, private array $arguments, Request $request, ?int $request_type)
    {
        parent::__construct($kernel, $request, $request_type);
        if (!$controller instanceof Controller_Event) {
            $controller = new Controller_Event($kernel, $controller, $request, $request_type);
        }
        $this->controller_event = $controller;
    }
    public function get_controller(): callable
    {
        return $this->controller_event->get_controller();
    }
    /**
     * @param list<object>|null $attributes
     */
    public function set_controller(callable $controller, ?array $attributes = null): void
    {
        $this->controller_event->set_controller($controller, $attributes);
        unset($this->named_arguments);
    }
    /**
     * @return list<mixed>
     */
    public function get_arguments(): array
    {
        return $this->arguments;
    }
    /**
     * @param list<mixed> $arguments
     */
    public function set_arguments(array $arguments): void
    {
        $this->arguments = $arguments;
        unset($this->named_arguments);
    }
    /**
     * @return array<string, mixed>
     */
    public function get_named_arguments(): array
    {
        if (isset($this->named_arguments)) {
            return $this->named_arguments;
        }
        $named_arguments = [];
        $arguments = $this->arguments;
        foreach ($this->controller_event->get_controller_reflector()->get_parameters() as $i => $param) {
            if ($param->is_variadic()) {
                $named_arguments[$param->name] = \array_slice($arguments, $i);
                break;
            }
            if (\array_key_exists($i, $arguments)) {
                $named_arguments[$param->name] = $arguments[$i];
            } elseif ($param->is_defaultvalue_available()) {
                $named_arguments[$param->name] = $param->get_default_value();
            }
        }
        return $this->named_arguments = $named_arguments;
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
        return $this->controller_event->get_attributes($class_name);
    }
    public function evaluate(mixed $value, ?Expression_Language $expression_language): mixed
    {
        if (!$value instanceof \Closure && !$value instanceof Expression) {
            return $value;
        }
        return $this->controller_event->evaluate($value, $expression_language, $this->get_named_arguments());
    }
}
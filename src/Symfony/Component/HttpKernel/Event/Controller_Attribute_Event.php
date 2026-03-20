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

use Psr\Event_Dispatcher\Stoppable_Event_Interface;
use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Expression_Language\Expression_Language;
/**
 * Event dispatched for each controller attribute.
 *
 * @template T of object
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final readonly class Controller_Attribute_Event implements Stoppable_Event_Interface
{
    private string|array|object|null $controller;
    /**
     * @param T $attribute
     */
    public function __construct(
        /** @var T */
        public object $attribute,
        public Kernel_Event $kernel_event,
        private ?Expression_Language $expression_language = null
    )
    {
        $this->controller = match (true) {
            $kernel_event instanceof Controller_Event => $kernel_event->get_controller(),
            $kernel_event instanceof Controller_Arguments_Event => $kernel_event->get_controller(),
            default => null,
        };
    }
    public function is_propagation_stopped(): bool
    {
        $event = $this->kernel_event;
        if ($event->is_propagation_stopped()) {
            return true;
        }
        if (!$this->controller) {
            return false;
        }
        $controller = match (true) {
            $event instanceof Controller_Event => $event->get_controller(),
            $event instanceof Controller_Arguments_Event => $event->get_controller(),
        };
        return $controller instanceof \Closure ? $controller != $this->controller : $controller !== $this->controller;
    }
    public function evaluate(mixed $value, ?Expression_Language $expression_language = null): mixed
    {
        if (!$value instanceof \Closure && !$value instanceof Expression) {
            return $value;
        }
        $event = $this->kernel_event;
        $expression_language ??= $this->expression_language;
        return match (true) {
            $event instanceof Controller_Event => $event->evaluate($value, $expression_language),
            $event instanceof Controller_Arguments_Event => $event->evaluate($value, $expression_language),
            ($m = $event->controller_metadata ?? null) instanceof Controller_Metadata => $m->evaluate($value, $expression_language),
        };
    }
}
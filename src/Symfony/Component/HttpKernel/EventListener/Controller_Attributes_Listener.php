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
namespace Symfony\Component\Http_Kernel\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\Http_Kernel\Event\Controller_Arguments_Event;
use Symfony\Component\Http_Kernel\Event\Controller_Attribute_Event;
use Symfony\Component\Http_Kernel\Event\Controller_Event;
use Symfony\Component\Http_Kernel\Event\Kernel_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Contracts\Event_Dispatcher\Event_Dispatcher_Interface;
// Help opcache.preload discover always-needed symbols
class_exists(Controller_Attribute_Event::class);
class_exists(Expression_Language::class);
/**
 * Dispatches events for controller attributes.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Controller_Attributes_Listener implements Event_Subscriber_Interface
{
    /**
     * @param array<string, array<class-string, true>> $attributesWithListenersByEvent
     */
    public function __construct(private readonly array $attributes_with_listeners_by_event, private ?Expression_Language $expression_language = null)
    {
        $this->expression_language ??= class_exists(Expression_Language::class, false) ? new Expression_Language() : null;
    }
    private static array $attribute_hierarchy_cache = [];
    public function before_controller(Controller_Event|Controller_Arguments_Event $event, string $event_name, Event_Dispatcher_Interface $dispatcher): void
    {
        $controller = $event->get_controller();
        dispatch_attributes:
        foreach ($event->get_attributes('*') as $attribute) {
            if (!$attribute_event_names = $this->get_attribute_event_names($attribute, $event_name)) {
                continue;
            }
            foreach ($attribute_event_names as $attribute_event_name) {
                $dispatcher->dispatch(new Controller_Attribute_Event($attribute, $event, $this->expression_language), $attribute_event_name);
                if ($event->is_propagation_stopped()) {
                    return;
                }
            }
            $c = $event->get_controller();
            if ($c instanceof \Closure ? $c != $controller : $c !== $controller) {
                $controller = $c;
                goto dispatch_attributes;
            }
        }
    }
    public function after_controller(Kernel_Event $event, string $event_name, Event_Dispatcher_Interface $dispatcher): void
    {
        $attributes = $event->controller_metadata?->get_attributes('*') ?? [];
        for ($i = \count($attributes) - 1; $i >= 0; --$i) {
            $attribute = $attributes[$i];
            $attribute_event_names = $this->get_attribute_event_names($attribute, $event_name);
            for ($j = \count($attribute_event_names) - 1; $j >= 0; --$j) {
                $dispatcher->dispatch(new Controller_Attribute_Event($attribute, $event, $this->expression_language), $attribute_event_names[$j]);
                if ($event->is_propagation_stopped()) {
                    return;
                }
            }
        }
    }
    private function get_attribute_event_names(object $attribute, string $event_name): array
    {
        if (!$attributes_with_listeners = $this->attributes_with_listeners_by_event[$event_name] ?? []) {
            return [];
        }
        $names = [];
        $class = $attribute::class;
        $hierarchy = self::$attribute_hierarchy_cache[$class] ??= [$class => $class] + class_parents($class) + class_implements($class);
        foreach ($hierarchy as $class) {
            if (isset($attributes_with_listeners[$class])) {
                $names[] = $event_name . '.' . $class;
            }
        }
        return $names;
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::CONTROLLER => ['beforeController', -10000], Kernel_Events::CONTROLLER_ARGUMENTS => ['beforeController', -10000], Kernel_Events::VIEW => ['afterController', 10000], Kernel_Events::RESPONSE => ['afterController', 10000], Kernel_Events::EXCEPTION => ['afterController', 10000], Kernel_Events::FINISH_REQUEST => ['afterController', 10000]];
    }
}
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
namespace Symfony\Component\Event_Dispatcher\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Argument\Service_Closure_Argument;
use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Contracts\Event_Dispatcher\Event;
/**
 * Compiler pass to register tagged services for an event dispatcher.
 */
class Register_Listeners_Pass implements Compiler_Pass_Interface
{
    private array $hot_path_events = [];
    private array $no_preload_events = [];
    /**
     * @return $this
     */
    public function set_hot_path_events(array $hot_path_events): static
    {
        $this->hot_path_events = array_flip($hot_path_events);
        return $this;
    }
    /**
     * @return $this
     */
    public function set_no_preload_events(array $no_preload_events): static
    {
        $this->no_preload_events = array_flip($no_preload_events);
        return $this;
    }
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('event_dispatcher') && !$container->has_alias('event_dispatcher')) {
            return;
        }
        $aliases = [];
        if ($container->has_parameter('event_dispatcher.event_aliases')) {
            $aliases = $container->get_parameter('event_dispatcher.event_aliases');
        }
        $global_dispatcher_definition = $container->find_definition('event_dispatcher');
        foreach ($container->find_tagged_service_ids('kernel.event_listener', true) as $id => $events) {
            $no_preload = 0;
            $resolved_events = [];
            foreach ($events as $event) {
                if (!isset($event['event'])) {
                    if ($container->get_definition($id)->has_tag('kernel.event_subscriber')) {
                        continue;
                    }
                    $event['method'] ??= '__invoke';
                    $event_names = $this->get_event_from_type_declaration($container, $id, $event['method']);
                } else {
                    $event_names = [$event['event']];
                }
                foreach ($event_names as $event_name) {
                    $event['event'] = $aliases[$event_name] ?? $event_name;
                    $resolved_events[] = $event;
                }
            }
            foreach ($resolved_events as $event) {
                $priority = $event['priority'] ?? 0;
                if (!isset($event['method'])) {
                    $event['method'] = 'on' . preg_replace_callback(['/(?<=\b|_)[a-z]/i', '/[^a-z0-9]/i'], static fn($matches) => strtoupper($matches[0]), (string) $event['event']);
                    $event['method'] = preg_replace('/[^a-z0-9]/i', '', $event['method']);
                    if (null !== ($class = $container->get_definition($id)->get_class()) && ($r = $container->get_reflection_class($class, false)) && !$r->has_method($event['method'])) {
                        if (!$r->has_method('__invoke')) {
                            throw new InvalidArgumentException(\sprintf('None of the "%s" or "__invoke" methods exist for the service "%s". Please define the "method" attribute on "kernel.event_listener" tags.', $event['method'], $id));
                        }
                        $event['method'] = '__invoke';
                    }
                }
                $dispatcher_definition = $global_dispatcher_definition;
                if (isset($event['dispatcher'])) {
                    $dispatcher_definition = $container->find_definition($event['dispatcher']);
                }
                $dispatcher_definition->add_method_call('addListener', [$event['event'], [new Service_Closure_Argument(new Reference($id)), $event['method']], $priority]);
                if (isset($this->hot_path_events[$event['event']])) {
                    $container->get_definition($id)->add_tag('container.hot_path');
                } elseif (isset($this->no_preload_events[$event['event']])) {
                    ++$no_preload;
                }
            }
            if ($no_preload && \count($events) === $no_preload) {
                $container->get_definition($id)->add_tag('container.no_preload');
            }
        }
        $extracting_dispatcher = new Extracting_Event_Dispatcher();
        foreach ($container->find_tagged_service_ids('kernel.event_subscriber', true) as $id => $tags) {
            $def = $container->get_definition($id);
            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $def->get_class();
            if (!$r = $container->get_reflection_class($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            if (!$r->is_subclass_of(Event_Subscriber_Interface::class)) {
                throw new InvalidArgumentException(\sprintf('Service "%s" must implement interface "%s".', $id, Event_Subscriber_Interface::class));
            }
            $class = $r->name;
            $dispatcher_definitions = [];
            foreach ($tags as $attributes) {
                if (!isset($attributes['dispatcher'])) {
                    continue;
                }
                if (isset($dispatcher_definitions[$attributes['dispatcher']])) {
                    continue;
                }
                $dispatcher_definitions[$attributes['dispatcher']] = $container->find_definition($attributes['dispatcher']);
            }
            if (!$dispatcher_definitions) {
                $dispatcher_definitions = [$global_dispatcher_definition];
            }
            $no_preload = 0;
            Extracting_Event_Dispatcher::$aliases = $aliases;
            Extracting_Event_Dispatcher::$subscriber = $class;
            $extracting_dispatcher->add_subscriber($extracting_dispatcher);
            foreach ($extracting_dispatcher->listeners as $args) {
                $args[1] = [new Service_Closure_Argument(new Reference($id)), $args[1]];
                foreach ($dispatcher_definitions as $dispatcher_definition) {
                    $dispatcher_definition->add_method_call('addListener', $args);
                }
                if (isset($this->hot_path_events[$args[0]])) {
                    $container->get_definition($id)->add_tag('container.hot_path');
                } elseif (isset($this->no_preload_events[$args[0]])) {
                    ++$no_preload;
                }
            }
            if ($no_preload && \count($extracting_dispatcher->listeners) === $no_preload) {
                $container->get_definition($id)->add_tag('container.no_preload');
            }
            $extracting_dispatcher->listeners = [];
            Extracting_Event_Dispatcher::$aliases = [];
        }
    }
    /**
     * @return string[]
     */
    private function get_event_from_type_declaration(Container_Builder $container, string $id, string $method): array
    {
        if (null === ($class = $container->get_definition($id)->get_class()) || !($r = $container->get_reflection_class($class, false)) || !$r->has_method($method) || 1 > ($m = $r->get_method($method))->get_number_of_parameters() || !(($type = $m->get_parameters()[0]->get_type()) instanceof \ReflectionNamedType || $type instanceof \ReflectionUnionType)) {
            throw new InvalidArgumentException(\sprintf('Service "%s" must define the "event" attribute on "kernel.event_listener" tags.', $id));
        }
        $types = $type instanceof \ReflectionUnionType ? $type->get_types() : [$type];
        $names = [];
        foreach ($types as $type) {
            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }
            if ($type->is_builtin()) {
                continue;
            }
            if (Event::class === $name = $type->get_name()) {
                continue;
            }
            $names[] = $name;
        }
        if (!$names) {
            throw new InvalidArgumentException(\sprintf('Service "%s" must define the "event" attribute on "kernel.event_listener" tags.', $id));
        }
        return $names;
    }
}
/**
 * @internal
 */
class Extracting_Event_Dispatcher extends Event_Dispatcher implements Event_Subscriber_Interface
{
    public array $listeners = [];
    public static array $aliases = [];
    public static string $subscriber;
    public function add_listener(string $event_name, callable|array $listener, int $priority = 0): void
    {
        $this->listeners[] = [$event_name, $listener[1], $priority];
    }
    public static function get_subscribed_events(): array
    {
        $events = [];
        foreach ([self::$subscriber, 'getSubscribedEvents']() as $event_name => $params) {
            $events[self::$aliases[$event_name] ?? $event_name] = $params;
        }
        return $events;
    }
}
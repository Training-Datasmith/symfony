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

use Psr\Container\Container_Interface as PsrContainerInterface;
use Symfony\Component\Dependency_Injection\Argument\Bound_Argument;
use Symfony\Component\Dependency_Injection\Attribute\Autowire;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Definition;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Dependency_Injection\Typed_Reference;
use Symfony\Contracts\Service\Attribute\Subscribed_Service;
use Symfony\Contracts\Service\Service_Collection_Interface;
use Symfony\Contracts\Service\Service_Provider_Interface;
use Symfony\Contracts\Service\Service_Subscriber_Interface;
/**
 * Compiler pass to register tagged services that require a service locator.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Register_Service_Subscribers_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = true;
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (!$value instanceof Definition || $value->is_abstract() || $value->is_synthetic() || !$value->has_tag('container.service_subscriber')) {
            return parent::process_value($value, $is_root);
        }
        $service_map = [];
        $autowire = $value->is_autowired();
        foreach ($value->get_tag('container.service_subscriber') as $attributes) {
            if (!$attributes) {
                $autowire = true;
                continue;
            }
            ksort($attributes);
            if ([] !== array_diff(array_keys($attributes), ['id', 'key'])) {
                throw new InvalidArgumentException(\sprintf('The "container.service_subscriber" tag accepts only the "key" and "id" attributes, "%s" given for service "%s".', implode('", "', array_keys($attributes)), $this->current_id));
            }
            if (!\array_key_exists('id', $attributes)) {
                throw new InvalidArgumentException(\sprintf('Missing "id" attribute on "container.service_subscriber" tag with key="%s" for service "%s".', $attributes['key'], $this->current_id));
            }
            if (!\array_key_exists('key', $attributes)) {
                $attributes['key'] = $attributes['id'];
            }
            if (isset($service_map[$attributes['key']])) {
                continue;
            }
            $service_map[$attributes['key']] = new Reference($attributes['id']);
        }
        $class = $value->get_class();
        if (!$r = $this->container->get_reflection_class($class)) {
            throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $this->current_id));
        }
        if (!$r->is_subclass_of(Service_Subscriber_Interface::class)) {
            throw new InvalidArgumentException(\sprintf('Service "%s" must implement interface "%s".', $this->current_id, Service_Subscriber_Interface::class));
        }
        $class = $r->name;
        $subscriber_map = [];
        foreach ($class::get_subscribed_services() as $key => $type) {
            $attributes = [];
            if (!isset($service_map[$key]) && $type instanceof Autowire) {
                $subscriber_map[$key] = $type;
                continue;
            }
            if ($type instanceof Subscribed_Service) {
                $key = $type->key ?? $key;
                $attributes = $type->attributes;
                $type = ($type->nullable ? '?' : '') . ($type->type ?? throw new InvalidArgumentException(\sprintf('When "%s::getSubscribedServices()" returns "%s", a type must be set.', $class, Subscribed_Service::class)));
            }
            if (!\is_string($type) || !preg_match('/(?(DEFINE)(?<cn>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+))(?(DEFINE)(?<fqcn>(?&cn)(?:\\\\(?&cn))*+))^\??(?&fqcn)(?:(?:\|(?&fqcn))*+|(?:&(?&fqcn))*+)$/', $type)) {
                throw new InvalidArgumentException(\sprintf('"%s::getSubscribedServices()" must return valid PHP types for service "%s" key "%s", "%s" returned.', $class, $this->current_id, $key, \is_string($type) ? $type : get_debug_type($type)));
            }
            $optional_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
            if ('?' === $type[0]) {
                $type = substr($type, 1);
                $optional_behavior = Container_Interface::IGNORE_ON_INVALID_REFERENCE;
            }
            if (\is_int($name = $key)) {
                $key = $type;
                $name = null;
            }
            if (!isset($service_map[$key])) {
                if (!$autowire) {
                    throw new InvalidArgumentException(\sprintf('Service "%s" misses a "container.service_subscriber" tag with "key"/"id" attributes corresponding to entry "%s" as returned by "%s::getSubscribedServices()".', $this->current_id, $key, $class));
                }
                $service_map[$key] = new Reference($type);
            }
            if ($name) {
                if (false !== $i = strpos($name, '::get')) {
                    $name = lcfirst(substr($name, 5 + $i));
                } elseif (str_contains($name, '::')) {
                    $name = null;
                }
            }
            if (null !== $name && !$this->container->has($name) && !$this->container->has($type . ' $' . $name)) {
                $camel_case_name = lcfirst(str_replace(' ', '', ucwords((string) preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $name))));
                $name = $this->container->has($type . ' $' . $camel_case_name) ? $camel_case_name : $name;
            }
            $subscriber_map[$key] = new Typed_Reference((string) $service_map[$key], $type, $optional_behavior, $name, $attributes);
            unset($service_map[$key]);
        }
        if ($service_map = array_keys($service_map)) {
            $message = \sprintf(1 < \count($service_map) ? 'keys "%s" do' : 'key "%s" does', str_replace('%', '%%', implode('", "', $service_map)));
            throw new InvalidArgumentException(\sprintf('Service %s not exist in the map returned by "%s::getSubscribedServices()" for service "%s".', $message, $class, $this->current_id));
        }
        $locator_ref = Service_Locator_Tag_Pass::register($this->container, $subscriber_map, $this->current_id);
        $value->add_tag('container.service_subscriber.locator', ['id' => (string) $locator_ref]);
        $value->set_bindings([Psr_Container_Interface::class => new Bound_Argument($locator_ref, false), Service_Provider_Interface::class => new Bound_Argument($locator_ref, false), Service_Collection_Interface::class => new Bound_Argument($locator_ref, false)] + $value->get_bindings());
        return parent::process_value($value);
    }
}
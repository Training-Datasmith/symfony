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
namespace Symfony\Component\Dependency_Injection\Attribute;

use Symfony\Component\Dependency_Injection\Argument\Service_Locator_Argument;
use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Typed_Reference;
use Symfony\Contracts\Service\Attribute\Subscribed_Service;
use Symfony\Contracts\Service\Service_Subscriber_Interface;
/**
 * Autowires a service locator based on a tag name or an explicit list of key => service-type pairs.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Autowire_Locator extends Autowire
{
    /**
     * @see ServiceSubscriberInterface::getSubscribedServices()
     *
     * @param string|array<string|Autowire|SubscribedService> $services       A tag name or an explicit list of service ids
     * @param string|null                                     $indexAttribute The name of the attribute that defines the key referencing each service in the locator
     * @param string|string[]                                 $exclude        A service id or a list of service ids to exclude
     * @param bool                                            $excludeSelf    Whether to automatically exclude the referencing service from the locator
     */
    public function __construct(string|array $services, ?string $index_attribute = null, string|array|null $exclude = [], bool|string|null $exclude_self = true, ...$_)
    {
        if (\func_num_args() > 4 || !\is_bool($exclude_self) || null === $exclude || \is_string($exclude) && str_starts_with($exclude, 'get') && !\array_key_exists('defaultIndexMethod', $_)) {
            [, , $default_index_method, $default_priority_method, $exclude, $exclude_self] = \func_get_args() + [2 => null, null, [], true];
        } else {
            $default_index_method = \array_key_exists('defaultIndexMethod', $_) ? $_['defaultIndexMethod'] : false;
            $default_priority_method = \array_key_exists('defaultPriorityMethod', $_) ? $_['defaultPriorityMethod'] : false;
        }
        if (\is_string($services)) {
            if (false !== $default_index_method || false !== $default_priority_method) {
                parent::__construct(new Service_Locator_Argument(new Tagged_Iterator_Argument($services, $index_attribute, $default_index_method, true, $default_priority_method, (array) $exclude, $exclude_self)));
                return;
            }
            parent::__construct(new Service_Locator_Argument(new Tagged_Iterator_Argument($services, $index_attribute, true, (array) $exclude, $exclude_self)));
            return;
        }
        if (false !== $default_index_method || false !== $default_priority_method) {
            trigger_deprecation('symfony/dependency-injection', '8.1', 'The $defaultIndexMethod and $defaultPriorityMethod arguments of tagged locators and iterators attributes are deprecated, use the #[AsTaggedItem] attribute instead of default methods.');
        }
        $references = [];
        foreach ($services as $key => $type) {
            $attributes = [];
            if ($type instanceof Autowire) {
                $references[$key] = $type;
                continue;
            }
            if ($type instanceof Subscribed_Service) {
                $key = $type->key ?? $key;
                $attributes = $type->attributes;
                $type = ($type->nullable ? '?' : '') . ($type->type ?? throw new InvalidArgumentException(\sprintf('When "%s" is used, a type must be set.', Subscribed_Service::class)));
            }
            if (!\is_string($type) || !preg_match('/(?(DEFINE)(?<cn>[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+))(?(DEFINE)(?<fqcn>(?&cn)(?:\\\\(?&cn))*+))^\??(?&fqcn)(?:(?:\|(?&fqcn))*+|(?:&(?&fqcn))*+)$/', $type)) {
                throw new InvalidArgumentException(\sprintf('"%s" is not a PHP type for key "%s".', \is_string($type) ? $type : get_debug_type($type), $key));
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
            $references[$key] = new Typed_Reference($type, $type, $optional_behavior, $name, $attributes);
        }
        parent::__construct(new Service_Locator_Argument($references));
    }
}
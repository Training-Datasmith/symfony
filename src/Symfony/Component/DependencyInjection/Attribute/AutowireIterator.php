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

use Symfony\Component\Dependency_Injection\Argument\Tagged_Iterator_Argument;
/**
 * Autowires an iterator of services based on a tag name.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Autowire_Iterator extends Autowire
{
    /**
     * @see ServiceSubscriberInterface::getSubscribedServices()
     *
     * @param string          $tag            A tag name to search for to populate the iterator
     * @param string|null     $indexAttribute The name of the attribute that defines the key referencing each service in the tagged collection
     * @param string|string[] $exclude        A service id or a list of service ids to exclude
     * @param bool            $excludeSelf    Whether to automatically exclude the referencing service from the iterator
     */
    public function __construct(string $tag, ?string $index_attribute = null, string|array|null $exclude = [], bool|string|null $exclude_self = true, ...$_)
    {
        if (\func_num_args() > 4 || !\is_bool($exclude_self) || null === $exclude || \is_string($exclude) && str_starts_with($exclude, 'get') && !\array_key_exists('defaultIndexMethod', $_)) {
            [, , $default_index_method, $default_priority_method, $exclude, $exclude_self] = \func_get_args() + [2 => null, null, [], true];
        } else {
            $default_index_method = \array_key_exists('defaultIndexMethod', $_) ? $_['defaultIndexMethod'] : false;
            $default_priority_method = \array_key_exists('defaultPriorityMethod', $_) ? $_['defaultPriorityMethod'] : false;
        }
        if (false !== $default_index_method || false !== $default_priority_method) {
            parent::__construct(new Tagged_Iterator_Argument($tag, $index_attribute, $default_index_method, false, $default_priority_method, (array) $exclude, $exclude_self));
            return;
        }
        parent::__construct(new Tagged_Iterator_Argument($tag, $index_attribute, false, (array) $exclude, $exclude_self));
    }
}
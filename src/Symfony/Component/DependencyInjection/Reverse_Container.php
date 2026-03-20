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
namespace Symfony\Component\Dependency_Injection;

use Psr\Container\Container_Interface;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
/**
 * Turns public and "container.reversible" services back to their ids.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final readonly class Reverse_Container
{
    private \Closure $get_service_id;
    public function __construct(private Container $service_container, private Container_Interface $reversible_locator, private string $tag_name = 'container.reversible')
    {
        $this->get_service_id = \Closure::bind(fn(object $service): ?string => (array_search($service, $this->services, true) ?: array_search($service, $this->privates, true)) ?: null, $service_container, Container::class);
    }
    /**
     * Returns the id of the passed object when it exists as a service.
     *
     * To be reversible, services need to be either public or be tagged with "container.reversible".
     */
    public function get_id(object $service): ?string
    {
        if ($this->service_container === $service) {
            return 'service_container';
        }
        if (null === $id = ($this->get_service_id)($service)) {
            return null;
        }
        if ($this->service_container->has($id) || $this->reversible_locator->has($id)) {
            return $id;
        }
        return null;
    }
    /**
     * @throws ServiceNotFoundException When the service is not reversible
     */
    public function get_service(string $id): object
    {
        if ($this->reversible_locator->has($id)) {
            return $this->reversible_locator->get($id);
        }
        if (isset($this->service_container->get_removed_ids()[$id])) {
            throw new Service_Not_Found_Exception($id, null, null, [], \sprintf('The "%s" service is private and cannot be accessed by reference. You should either make it public, or tag it as "%s".', $id, $this->tag_name));
        }
        return $this->service_container->get($id);
    }
}
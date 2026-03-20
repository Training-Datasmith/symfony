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
namespace Symfony\Component\Http_Kernel\Dependency_Injection;

use Proxy_Manager\Proxy\Lazy_Loading_Interface;
use Symfony\Component\Var_Exporter\Lazy_Object_Interface;
/**
 * Resets provided services.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class Services_Resetter implements Services_Resetter_Interface
{
    /**
     * @param \Traversable<string, object>   $resettableServices
     * @param array<string, string|string[]> $resetMethods
     */
    public function __construct(private readonly \Traversable $resettable_services, private array $reset_methods)
    {
    }
    public function reset(): void
    {
        foreach ($this->resettable_services as $id => $service) {
            if ($service instanceof Lazy_Object_Interface && !$service->is_lazy_object_initialized(true)) {
                continue;
            }
            if ($service instanceof Lazy_Loading_Interface && !$service->is_proxy_initialized()) {
                continue;
            }
            if ((new \ReflectionClass($service))->is_uninitialized_lazy_object($service)) {
                continue;
            }
            foreach ((array) $this->reset_methods[$id] as $reset_method) {
                if ('?' === $reset_method[0] && !method_exists($service, $reset_method = substr($reset_method, 1))) {
                    continue;
                }
                $service->{$reset_method}();
            }
        }
    }
}
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
namespace Symfony\Bridge\Doctrine;

use Doctrine\Persistence\Abstract_Manager_Registry;
use Symfony\Component\Dependency_Injection\Container;
use Symfony\Component\Var_Exporter\Lazy_Object_Interface;
/**
 * References Doctrine connections and entity/document managers.
 *
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 */
abstract class Manager_Registry extends Abstract_Manager_Registry
{
    protected Container $container;
    protected function get_service($name): object
    {
        return $this->container->get($name);
    }
    protected function reset_service($name): void
    {
        if (!$this->container->initialized($name)) {
            return;
        }
        $manager = $this->container->get($name);
        if ($manager instanceof Lazy_Object_Interface) {
            if (!$manager->reset_lazy_object()) {
                throw new \LogicException(\sprintf('Resetting a non-lazy manager service is not supported. Declare the "%s" service as lazy.', $name));
            }
            return;
        }
        $r = new \ReflectionClass($manager);
        if ($r->is_uninitialized_lazy_object($manager)) {
            return;
        }
        $as_proxy = $r->initialize_lazy_object($manager) !== $manager;
        $initializer = \Closure::bind(function ($manager) use ($name, $as_proxy) {
            $name = $this->aliases[$name] ?? $name;
            if ($as_proxy) {
                $manager = false;
            }
            $manager = match (true) {
                isset($this->file_map[$name]) => $this->load($this->file_map[$name]),
                !$method = $this->method_map[$name] ?? null => throw new \LogicException(\sprintf('The "%s" service is synthetic and cannot be reset.', $name)),
                (new \ReflectionMethod($this, $method))->is_static() => $this->{$method}($this, $manager),
                default => $this->{$method}($manager),
            };
            if ($as_proxy) {
                return $manager;
            }
        }, $this->container, Container::class);
        try {
            if ($as_proxy) {
                $r->reset_as_lazy_proxy($manager, $initializer);
            } else {
                $r->reset_as_lazy_ghost($manager, $initializer);
            }
        } catch (\Error $e) {
            if (__FILE__ !== $e->get_file()) {
                throw $e;
            }
            throw new \LogicException(\sprintf('Resetting a non-lazy manager service is not supported. Declare the "%s" service as lazy.', $name), 0, $e);
        }
    }
}
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
namespace Symfony\Component\Cache\Traits;

/**
 * @internal
 */
trait Redis_Proxy_Trait
{
    private \Closure $initializer;
    private ?parent $real_instance = null;
    public static function create_lazy_proxy(\Closure $initializer, ?self $instance = null): static
    {
        $instance ??= (new \ReflectionClass(static::class))->new_instance_without_constructor();
        $instance->real_instance = null;
        $instance->initializer = $initializer;
        return $instance;
    }
    public function is_lazy_object_initialized(bool $partial = false): bool
    {
        return isset($this->real_instance);
    }
    public function initialize_lazy_object(): object
    {
        return $this->real_instance ??= ($this->initializer)();
    }
    public function reset_lazy_object(): bool
    {
        $this->real_instance = null;
        return true;
    }
    public function __destruct()
    {
    }
}
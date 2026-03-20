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
namespace Symfony\Component\Http_Foundation\Session;

use Symfony\Component\Http_Foundation\Session\Attribute\Attribute_Bag;
use Symfony\Component\Http_Foundation\Session\Attribute\Attribute_Bag_Interface;
use Symfony\Component\Http_Foundation\Session\Flash\Flash_Bag;
use Symfony\Component\Http_Foundation\Session\Flash\Flash_Bag_Interface;
use Symfony\Component\Http_Foundation\Session\Storage\Metadata_Bag;
use Symfony\Component\Http_Foundation\Session\Storage\Native_Session_Storage;
use Symfony\Component\Http_Foundation\Session\Storage\Session_Storage_Interface;
// Help opcache.preload discover always-needed symbols
class_exists(Attribute_Bag::class);
class_exists(Flash_Bag::class);
class_exists(Session_Bag_Proxy::class);
/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Drak <drak@zikula.org>
 *
 * @implements \IteratorAggregate<string, mixed>
 */
class Session implements Flash_Bag_Aware_Session_Interface, \IteratorAggregate, \Countable
{
    private readonly string $flash_name;
    private readonly string $attribute_name;
    private array $data = [];
    private int $usage_index = 0;
    private readonly ?\Closure $usage_reporter;
    public function __construct(protected ?Session_Storage_Interface $storage = new Native_Session_Storage(), ?Attribute_Bag_Interface $attributes = null, ?Flash_Bag_Interface $flashes = null, ?callable $usage_reporter = null)
    {
        $this->usage_reporter = null === $usage_reporter ? null : $usage_reporter(...);
        $attributes ??= new Attribute_Bag();
        $this->attribute_name = $attributes->get_name();
        $this->register_bag($attributes);
        $flashes ??= new Flash_Bag();
        $this->flash_name = $flashes->get_name();
        $this->register_bag($flashes);
    }
    public function start(): bool
    {
        return $this->storage->start();
    }
    public function has(string $name): bool
    {
        return $this->get_attribute_bag()->has($name);
    }
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->get_attribute_bag()->get($name, $default);
    }
    public function set(string $name, mixed $value): void
    {
        $this->get_attribute_bag()->set($name, $value);
    }
    public function all(): array
    {
        return $this->get_attribute_bag()->all();
    }
    public function replace(array $attributes): void
    {
        $this->get_attribute_bag()->replace($attributes);
    }
    public function remove(string $name): mixed
    {
        return $this->get_attribute_bag()->remove($name);
    }
    public function clear(): void
    {
        $this->get_attribute_bag()->clear();
    }
    public function is_started(): bool
    {
        return $this->storage->is_started();
    }
    /**
     * Returns an iterator for attributes.
     *
     * @return \ArrayIterator<string, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->get_attribute_bag()->all());
    }
    /**
     * Returns the number of attributes.
     */
    public function count(): int
    {
        return \count($this->get_attribute_bag()->all());
    }
    public function &get_usage_index(): int
    {
        return $this->usage_index;
    }
    /**
     * @internal
     */
    public function is_empty(): bool
    {
        if ($this->is_started()) {
            ++$this->usage_index;
            if ($this->usage_reporter && 0 <= $this->usage_index) {
                ($this->usage_reporter)();
            }
        }
        foreach ($this->data as &$data) {
            if ($data) {
                return false;
            }
        }
        return true;
    }
    public function invalidate(?int $lifetime = null): bool
    {
        $this->storage->clear();
        return $this->migrate(true, $lifetime);
    }
    public function migrate(bool $destroy = false, ?int $lifetime = null): bool
    {
        return $this->storage->regenerate($destroy, $lifetime);
    }
    public function save(): void
    {
        $this->storage->save();
    }
    public function get_id(): string
    {
        return $this->storage->get_id();
    }
    public function set_id(string $id): void
    {
        if ($this->storage->get_id() !== $id) {
            $this->storage->set_id($id);
        }
    }
    public function get_name(): string
    {
        return $this->storage->get_name();
    }
    public function set_name(string $name): void
    {
        $this->storage->set_name($name);
    }
    public function get_metadata_bag(): Metadata_Bag
    {
        ++$this->usage_index;
        if ($this->usage_reporter && 0 <= $this->usage_index) {
            ($this->usage_reporter)();
        }
        return $this->storage->get_metadata_bag();
    }
    public function register_bag(Session_Bag_Interface $bag): void
    {
        $this->storage->register_bag(new Session_Bag_Proxy($bag, $this->data, $this->usage_index, $this->usage_reporter));
    }
    public function get_bag(string $name): Session_Bag_Interface
    {
        $bag = $this->storage->get_bag($name);
        return method_exists($bag, 'getBag') ? $bag->get_bag() : $bag;
    }
    /**
     * Gets the flashbag interface.
     */
    public function get_flash_bag(): Flash_Bag_Interface
    {
        return $this->get_bag($this->flash_name);
    }
    /**
     * Gets the attributebag interface.
     *
     * Note that this method was added to help with IDE autocompletion.
     */
    private function get_attribute_bag(): Attribute_Bag_Interface
    {
        return $this->get_bag($this->attribute_name);
    }
}
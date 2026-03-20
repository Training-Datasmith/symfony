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
namespace Symfony\Component\Http_Foundation\Session\Attribute;

/**
 * This class relates to session attribute storage.
 *
 * @implements \IteratorAggregate<string, mixed>
 */
class Attribute_Bag implements Attribute_Bag_Interface, \IteratorAggregate, \Countable
{
    protected array $attributes = [];
    private string $name = 'attributes';
    /**
     * @param string $storageKey The key used to store attributes in the session
     */
    public function __construct(private readonly string $storage_key = '_sf2_attributes')
    {
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function set_name(string $name): void
    {
        $this->name = $name;
    }
    public function initialize(array &$attributes): void
    {
        $this->attributes =& $attributes;
    }
    public function get_storage_key(): string
    {
        return $this->storage_key;
    }
    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }
    public function get(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }
    public function set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }
    public function all(): array
    {
        return $this->attributes;
    }
    public function replace(array $attributes): void
    {
        $this->attributes = [];
        foreach ($attributes as $key => $value) {
            $this->set($key, $value);
        }
    }
    public function remove(string $name): mixed
    {
        $retval = null;
        if (\array_key_exists($name, $this->attributes)) {
            $retval = $this->attributes[$name];
            unset($this->attributes[$name]);
        }
        return $retval;
    }
    public function clear(): mixed
    {
        $return = $this->attributes;
        $this->attributes = [];
        return $return;
    }
    /**
     * Returns an iterator for attributes.
     *
     * @return \ArrayIterator<string, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->attributes);
    }
    /**
     * Returns the number of attributes.
     */
    public function count(): int
    {
        return \count($this->attributes);
    }
}
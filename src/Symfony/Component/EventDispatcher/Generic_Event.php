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
namespace Symfony\Component\Event_Dispatcher;

use Symfony\Contracts\Event_Dispatcher\Event;
/**
 * Event encapsulation class.
 *
 * Encapsulates events thus decoupling the observer from the subject they encapsulate.
 *
 * @author Drak <drak@zikula.org>
 *
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
class Generic_Event extends Event implements \ArrayAccess, \IteratorAggregate
{
    /**
     * Encapsulate an event with $subject and $arguments.
     *
     * @param mixed $subject   The subject of the event, usually an object or a callable
     * @param array $arguments Arguments to store in the event
     */
    public function __construct(protected mixed $subject = null, protected array $arguments = [])
    {
    }
    /**
     * Getter for subject property.
     */
    public function get_subject(): mixed
    {
        return $this->subject;
    }
    /**
     * Get argument by key.
     *
     * @throws \InvalidArgumentException if key is not found
     */
    public function get_argument(string $key): mixed
    {
        if ($this->has_argument($key)) {
            return $this->arguments[$key];
        }
        throw new \InvalidArgumentException(\sprintf('Argument "%s" not found.', $key));
    }
    /**
     * Add argument to event.
     *
     * @return $this
     */
    public function set_argument(string $key, mixed $value): static
    {
        $this->arguments[$key] = $value;
        return $this;
    }
    /**
     * Getter for all arguments.
     */
    public function get_arguments(): array
    {
        return $this->arguments;
    }
    /**
     * Set args property.
     *
     * @return $this
     */
    public function set_arguments(array $args = []): static
    {
        $this->arguments = $args;
        return $this;
    }
    /**
     * Has argument.
     */
    public function has_argument(string $key): bool
    {
        return \array_key_exists($key, $this->arguments);
    }
    /**
     * ArrayAccess for argument getter.
     *
     * @param string $key Array key
     *
     * @throws \InvalidArgumentException if key does not exist in $this->args
     */
    public function offsetGet(mixed $key): mixed
    {
        return $this->get_argument($key);
    }
    /**
     * ArrayAccess for argument setter.
     *
     * @param string $key Array key to set
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->set_argument($key, $value);
    }
    /**
     * ArrayAccess for unset argument.
     *
     * @param string $key Array key
     */
    public function offsetUnset(mixed $key): void
    {
        if ($this->has_argument($key)) {
            unset($this->arguments[$key]);
        }
    }
    /**
     * ArrayAccess has argument.
     *
     * @param string $key Array key
     */
    public function offsetExists(mixed $key): bool
    {
        return $this->has_argument($key);
    }
    /**
     * IteratorAggregate for iterating over the object like an array.
     *
     * @return \ArrayIterator<string, mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->arguments);
    }
}
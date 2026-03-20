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
namespace Symfony\Component\Http_Foundation\Session\Storage;

use Symfony\Component\Http_Foundation\Session\Session_Bag_Interface;
/**
 * MockArraySessionStorage mocks the session for unit tests.
 *
 * No PHP session is actually started since a session can be initialized
 * and shutdown only once per PHP execution cycle.
 *
 * When doing functional testing, you should use MockFileSessionStorage instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Drak <drak@zikula.org>
 */
class Mock_Array_Session_Storage implements Session_Storage_Interface
{
    protected string $id = '';
    protected bool $started = false;
    protected bool $closed = false;
    protected array $data = [];
    protected Metadata_Bag $metadata_bag;
    /**
     * @var SessionBagInterface[]
     */
    protected array $bags = [];
    public function __construct(protected string $name = 'MOCKSESSID', ?Metadata_Bag $meta_bag = null)
    {
        $this->set_metadata_bag($meta_bag);
    }
    public function set_session_data(array $array): void
    {
        $this->data = $array;
    }
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }
        if (!$this->id) {
            $this->id = $this->generate_id();
        }
        $this->load_session();
        return true;
    }
    public function regenerate(bool $destroy = false, ?int $lifetime = null): bool
    {
        if (!$this->started) {
            $this->start();
        }
        $this->metadata_bag->stamp_new($lifetime);
        $this->id = $this->generate_id();
        return true;
    }
    public function get_id(): string
    {
        return $this->id;
    }
    public function set_id(string $id): void
    {
        if ($this->started) {
            throw new \LogicException('Cannot set session ID after the session has started.');
        }
        $this->id = $id;
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function set_name(string $name): void
    {
        $this->name = $name;
    }
    public function save(): void
    {
        if (!$this->started || $this->closed) {
            throw new \RuntimeException('Trying to save a session that was not started yet or was already closed.');
        }
        // nothing to do since we don't persist the session data
        $this->closed = false;
        $this->started = false;
    }
    public function clear(): void
    {
        // clear out the bags
        foreach ($this->bags as $bag) {
            $bag->clear();
        }
        // clear out the session
        $this->data = [];
        // reconnect the bags to the session
        $this->load_session();
    }
    public function register_bag(Session_Bag_Interface $bag): void
    {
        $this->bags[$bag->get_name()] = $bag;
    }
    public function get_bag(string $name): Session_Bag_Interface
    {
        if (!isset($this->bags[$name])) {
            throw new \InvalidArgumentException(\sprintf('The SessionBagInterface "%s" is not registered.', $name));
        }
        if (!$this->started) {
            $this->start();
        }
        return $this->bags[$name];
    }
    public function is_started(): bool
    {
        return $this->started;
    }
    public function set_metadata_bag(?Metadata_Bag $bag): void
    {
        $this->metadata_bag = $bag ?? new Metadata_Bag();
    }
    /**
     * Gets the MetadataBag.
     */
    public function get_metadata_bag(): Metadata_Bag
    {
        return $this->metadata_bag;
    }
    /**
     * Generates a session ID.
     *
     * This doesn't need to be particularly cryptographically secure since this is just
     * a mock.
     */
    protected function generate_id(): string
    {
        return bin2hex(random_bytes(16));
    }
    protected function load_session(): void
    {
        $bags = array_merge($this->bags, [$this->metadata_bag]);
        foreach ($bags as $bag) {
            $key = $bag->get_storage_key();
            $this->data[$key] ??= [];
            $bag->initialize($this->data[$key]);
        }
        $this->started = true;
        $this->closed = false;
    }
}
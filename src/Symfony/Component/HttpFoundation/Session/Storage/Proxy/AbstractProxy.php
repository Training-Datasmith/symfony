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
namespace Symfony\Component\Http_Foundation\Session\Storage\Proxy;

/**
 * @author Drak <drak@zikula.org>
 */
abstract class Abstract_Proxy
{
    protected bool $wrapper = false;
    protected ?string $save_handler_name = null;
    /**
     * Gets the session.save_handler name.
     */
    public function get_save_handler_name(): ?string
    {
        return $this->save_handler_name;
    }
    /**
     * Is this proxy handler and instance of \SessionHandlerInterface.
     */
    public function is_session_handler_interface(): bool
    {
        return $this instanceof \Session_Handler_Interface;
    }
    /**
     * Returns true if this handler wraps an internal PHP session save handler using \SessionHandler.
     */
    public function is_wrapper(): bool
    {
        return $this->wrapper;
    }
    /**
     * Has a session started?
     */
    public function is_active(): bool
    {
        return \PHP_SESSION_ACTIVE === session_status();
    }
    /**
     * Gets the session ID.
     */
    public function get_id(): string
    {
        return session_id();
    }
    /**
     * Sets the session ID.
     *
     * @throws \LogicException
     */
    public function set_id(string $id): void
    {
        if ($this->is_active()) {
            throw new \LogicException('Cannot change the ID of an active session.');
        }
        session_id($id);
    }
    /**
     * Gets the session name.
     */
    public function get_name(): string
    {
        return session_name();
    }
    /**
     * Sets the session name.
     *
     * @throws \LogicException
     */
    public function set_name(string $name): void
    {
        if ($this->is_active()) {
            throw new \LogicException('Cannot change the name of an active session.');
        }
        session_name($name);
    }
}
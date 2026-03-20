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
namespace Symfony\Component\Http_Foundation;

use Symfony\Component\Http_Foundation\Exception\Session_Not_Found_Exception;
use Symfony\Component\Http_Foundation\Session\Session_Interface;
/**
 * Request stack that controls the lifecycle of requests.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class Request_Stack
{
    /**
     * @var Request[]
     */
    private array $requests = [];
    /**
     * @param Request[] $requests
     */
    public function __construct(array $requests = [])
    {
        foreach ($requests as $request) {
            $this->push($request);
        }
    }
    /**
     * Pushes a Request on the stack.
     *
     * This method should generally not be called directly as the stack
     * management should be taken care of by the application itself.
     */
    public function push(Request $request): void
    {
        $this->requests[] = $request;
    }
    /**
     * Pops the current request from the stack.
     *
     * This operation lets the current request go out of scope.
     *
     * This method should generally not be called directly as the stack
     * management should be taken care of by the application itself.
     */
    public function pop(): ?Request
    {
        if (!$this->requests) {
            return null;
        }
        return array_pop($this->requests);
    }
    public function get_current_request(): ?Request
    {
        return end($this->requests) ?: null;
    }
    /**
     * Gets the main request.
     *
     * Be warned that making your code aware of the main request
     * might make it un-compatible with other features of your framework
     * like ESI support.
     */
    public function get_main_request(): ?Request
    {
        if (!$this->requests) {
            return null;
        }
        return $this->requests[0];
    }
    /**
     * Returns the parent request of the current.
     *
     * Be warned that making your code aware of the parent request
     * might make it un-compatible with other features of your framework
     * like ESI support.
     *
     * If current Request is the main request, it returns null.
     */
    public function get_parent_request(): ?Request
    {
        $pos = \count($this->requests) - 2;
        return $this->requests[$pos] ?? null;
    }
    /**
     * Gets the current session.
     *
     * @throws SessionNotFoundException
     */
    public function get_session(): Session_Interface
    {
        if (null !== ($request = end($this->requests) ?: null) && $request->has_session()) {
            return $request->get_session();
        }
        throw new Session_Not_Found_Exception();
    }
    public function reset_request_formats(): void
    {
        static $reset_request_formats;
        $reset_request_formats ??= \Closure::bind(static fn(): null => self::$formats = null, null, Request::class);
        $reset_request_formats();
    }
}
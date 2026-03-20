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

use Symfony\Component\Http_Foundation\Request_Stack;
use Symfony\Component\Http_Foundation\Session\Storage\Session_Storage_Factory_Interface;
// Help opcache.preload discover always-needed symbols
class_exists(Session::class);
/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class Session_Factory implements Session_Factory_Interface
{
    private readonly ?\Closure $usage_reporter;
    public function __construct(private readonly Request_Stack $request_stack, private readonly Session_Storage_Factory_Interface $storage_factory, ?callable $usage_reporter = null)
    {
        $this->usage_reporter = null === $usage_reporter ? null : $usage_reporter(...);
    }
    public function create_session(): Session_Interface
    {
        return new Session($this->storage_factory->create_storage($this->request_stack->get_main_request()), null, null, $this->usage_reporter);
    }
}
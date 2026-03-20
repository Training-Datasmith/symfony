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
namespace Symfony\Component\Http_Kernel\Debug;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Request_Stack;
/**
 * A stack able to deal with virtual requests.
 *
 * @internal
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class Virtual_Request_Stack extends Request_Stack
{
    public function __construct(private readonly Request_Stack $decorated)
    {
    }
    public function push(Request $request): void
    {
        if ($request->attributes->has('_virtual_type')) {
            if ($this->decorated->get_current_request()) {
                throw new \LogicException('Cannot mix virtual and HTTP requests.');
            }
            parent::push($request);
            return;
        }
        $this->decorated->push($request);
    }
    public function pop(): ?Request
    {
        return $this->decorated->pop() ?? parent::pop();
    }
    public function get_current_request(): ?Request
    {
        return $this->decorated->get_current_request() ?? parent::get_current_request();
    }
    public function get_main_request(): ?Request
    {
        return $this->decorated->get_main_request() ?? parent::get_main_request();
    }
    public function get_parent_request(): ?Request
    {
        return $this->decorated->get_parent_request() ?? parent::get_parent_request();
    }
}
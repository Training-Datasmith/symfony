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
namespace Symfony\Component\Asset\Context;

use Symfony\Component\Http_Foundation\Request_Stack;
/**
 * Uses a RequestStack to populate the context.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Request_Stack_Context implements Context_Interface
{
    public function __construct(private readonly Request_Stack $request_stack, private readonly string $base_path = '', private readonly bool $secure = false)
    {
    }
    public function get_base_path(): string
    {
        if (!$request = $this->request_stack->get_main_request()) {
            return $this->base_path;
        }
        return $request->get_base_path();
    }
    public function is_secure(): bool
    {
        if (!$request = $this->request_stack->get_main_request()) {
            return $this->secure;
        }
        return $request->is_secure();
    }
}
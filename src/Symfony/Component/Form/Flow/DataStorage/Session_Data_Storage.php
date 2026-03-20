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
namespace Symfony\Component\Form\Flow\Data_Storage;

use Symfony\Component\Http_Foundation\Request_Stack;
/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Session_Data_Storage implements Data_Storage_Interface
{
    public function __construct(private readonly string $key, private readonly Request_Stack $request_stack)
    {
    }
    public function save(object|array $data): void
    {
        $this->request_stack->get_session()->set($this->key, $data);
    }
    public function load(object|array|null $default = null): object|array|null
    {
        return $this->request_stack->get_session()->get($this->key, $default);
    }
    public function clear(): void
    {
        $this->request_stack->get_session()->remove($this->key);
    }
}
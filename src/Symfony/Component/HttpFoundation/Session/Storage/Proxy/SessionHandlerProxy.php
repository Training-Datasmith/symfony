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

use Symfony\Component\Http_Foundation\Session\Storage\Handler\Strict_Session_Handler;
/**
 * @author Drak <drak@zikula.org>
 */
class Session_Handler_Proxy extends Abstract_Proxy implements \Session_Handler_Interface, \Session_Update_Timestamp_Handler_Interface
{
    public function __construct(protected \Session_Handler_Interface $handler)
    {
        $this->wrapper = $handler instanceof \Session_Handler;
        $this->save_handler_name = $this->wrapper || $handler instanceof Strict_Session_Handler && $handler->is_wrapper() ? \ini_get('session.save_handler') : 'user';
    }
    public function get_handler(): \Session_Handler_Interface
    {
        return $this->handler;
    }
    // \SessionHandlerInterface
    public function open(string $save_path, string $session_name): bool
    {
        return $this->handler->open($save_path, $session_name);
    }
    public function close(): bool
    {
        return $this->handler->close();
    }
    public function read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string|false
    {
        return $this->handler->read($session_id);
    }
    public function write(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        return $this->handler->write($session_id, $data);
    }
    public function destroy(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        return $this->handler->destroy($session_id);
    }
    public function gc(int $maxlifetime): int|false
    {
        return $this->handler->gc($maxlifetime);
    }
    public function validate_id(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        return !$this->handler instanceof \Session_Update_Timestamp_Handler_Interface || $this->handler->validate_id($session_id);
    }
    public function update_timestamp(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        return $this->handler instanceof \Session_Update_Timestamp_Handler_Interface ? $this->handler->update_timestamp($session_id, $data) : $this->write($session_id, $data);
    }
}
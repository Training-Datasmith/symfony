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
namespace Symfony\Component\Http_Foundation\Session\Storage\Handler;

/**
 * Migrating session handler for migrating from one handler to another. It reads
 * from the current handler and writes both the current and new ones.
 *
 * It ignores errors from the new handler.
 *
 * @author Ross Motley <ross.motley@amara.com>
 * @author Oliver Radwell <oliver.radwell@amara.com>
 */
class Migrating_Session_Handler implements \Session_Handler_Interface, \Session_Update_Timestamp_Handler_Interface
{
    private readonly \Session_Handler_Interface&\Session_Update_Timestamp_Handler_Interface $current_handler;
    private readonly \Session_Handler_Interface&\Session_Update_Timestamp_Handler_Interface $write_only_handler;
    public function __construct(\Session_Handler_Interface $current_handler, \Session_Handler_Interface $write_only_handler)
    {
        if (!$current_handler instanceof \Session_Update_Timestamp_Handler_Interface) {
            $current_handler = new Strict_Session_Handler($current_handler);
        }
        if (!$write_only_handler instanceof \Session_Update_Timestamp_Handler_Interface) {
            $write_only_handler = new Strict_Session_Handler($write_only_handler);
        }
        $this->current_handler = $current_handler;
        $this->write_only_handler = $write_only_handler;
    }
    public function close(): bool
    {
        $result = $this->current_handler->close();
        $this->write_only_handler->close();
        return $result;
    }
    public function destroy(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        $result = $this->current_handler->destroy($session_id);
        $this->write_only_handler->destroy($session_id);
        return $result;
    }
    public function gc(int $maxlifetime): int|false
    {
        $result = $this->current_handler->gc($maxlifetime);
        $this->write_only_handler->gc($maxlifetime);
        return $result;
    }
    public function open(string $save_path, string $session_name): bool
    {
        $result = $this->current_handler->open($save_path, $session_name);
        $this->write_only_handler->open($save_path, $session_name);
        return $result;
    }
    public function read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string
    {
        // No reading from new handler until switch-over
        return $this->current_handler->read($session_id);
    }
    public function write(
        #[\Sensitive_Parameter]
        string $session_id,
        string $session_data
    ): bool
    {
        $result = $this->current_handler->write($session_id, $session_data);
        $this->write_only_handler->write($session_id, $session_data);
        return $result;
    }
    public function validate_id(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        // No reading from new handler until switch-over
        return $this->current_handler->validate_id($session_id);
    }
    public function update_timestamp(
        #[\Sensitive_Parameter]
        string $session_id,
        string $session_data
    ): bool
    {
        $result = $this->current_handler->update_timestamp($session_id, $session_data);
        $this->write_only_handler->update_timestamp($session_id, $session_data);
        return $result;
    }
}
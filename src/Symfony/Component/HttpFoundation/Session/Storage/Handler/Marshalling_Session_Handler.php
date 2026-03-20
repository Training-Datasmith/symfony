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

use Symfony\Component\Cache\Marshaller\Marshaller_Interface;
/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class Marshalling_Session_Handler implements \Session_Handler_Interface, \Session_Update_Timestamp_Handler_Interface
{
    public function __construct(private readonly Abstract_Session_Handler $handler, private readonly Marshaller_Interface $marshaller)
    {
    }
    public function open(string $save_path, string $name): bool
    {
        return $this->handler->open($save_path, $name);
    }
    public function close(): bool
    {
        return $this->handler->close();
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
    public function read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string
    {
        return $this->marshaller->unmarshall($this->handler->read($session_id));
    }
    public function write(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        $failed = [];
        $marshalled_data = $this->marshaller->marshall(['data' => $data], $failed);
        if (isset($failed['data'])) {
            return false;
        }
        return $this->handler->write($session_id, $marshalled_data['data']);
    }
    public function validate_id(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        return $this->handler->validate_id($session_id);
    }
    public function update_timestamp(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        return $this->handler->update_timestamp($session_id, $data);
    }
}
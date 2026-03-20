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
 * Adds basic `SessionUpdateTimestampHandlerInterface` behaviors to another `SessionHandlerInterface`.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Strict_Session_Handler extends Abstract_Session_Handler
{
    private bool $do_destroy;
    public function __construct(private readonly \Session_Handler_Interface $handler)
    {
        if ($handler instanceof \Session_Update_Timestamp_Handler_Interface) {
            throw new \LogicException(\sprintf('"%s" is already an instance of "SessionUpdateTimestampHandlerInterface", you cannot wrap it with "%s".', get_debug_type($handler), self::class));
        }
    }
    /**
     * Returns true if this handler wraps an internal PHP session save handler using \SessionHandler.
     *
     * @internal
     */
    public function is_wrapper(): bool
    {
        return $this->handler instanceof \Session_Handler;
    }
    public function open(string $save_path, string $session_name): bool
    {
        parent::open($save_path, $session_name);
        return $this->handler->open($save_path, $session_name);
    }
    protected function do_read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string
    {
        return $this->handler->read($session_id);
    }
    public function update_timestamp(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        return $this->write($session_id, $data);
    }
    protected function do_write(
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
        $this->do_destroy = true;
        $destroyed = parent::destroy($session_id);
        return $this->do_destroy ? $this->do_destroy($session_id) : $destroyed;
    }
    protected function do_destroy(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        $this->do_destroy = false;
        return $this->handler->destroy($session_id);
    }
    public function close(): bool
    {
        return $this->handler->close();
    }
    public function gc(int $maxlifetime): int|false
    {
        return $this->handler->gc($maxlifetime);
    }
}
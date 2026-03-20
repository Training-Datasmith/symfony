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
 * Can be used in unit testing or in a situations where persisted sessions are not desired.
 *
 * @author Drak <drak@zikula.org>
 */
class Null_Session_Handler extends Abstract_Session_Handler
{
    public function close(): bool
    {
        return true;
    }
    public function validate_id(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        return true;
    }
    protected function do_read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string
    {
        return '';
    }
    public function update_timestamp(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        return true;
    }
    protected function do_write(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        return true;
    }
    protected function do_destroy(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        return true;
    }
    public function gc(int $maxlifetime): int|false
    {
        return 0;
    }
}
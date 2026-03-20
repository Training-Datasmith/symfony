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

use Predis\Response\Error_Interface;
use Relay\Relay;
/**
 * Redis based session storage handler based on the Redis class
 * provided by the PHP redis extension.
 *
 * @author Dalibor Karlović <dalibor@flexolabs.io>
 */
class Redis_Session_Handler extends Abstract_Session_Handler
{
    /**
     * Key prefix for shared environments.
     */
    private readonly string $prefix;
    /**
     * Time to live in seconds.
     */
    private readonly int|\Closure|null $ttl;
    /**
     * List of available options:
     *  * prefix: The prefix to use for the keys in order to avoid collision on the Redis server
     *  * ttl: The time to live in seconds.
     *
     * @throws \InvalidArgumentException When unsupported client or options are passed
     */
    public function __construct(private readonly \Redis|Relay|\Redis_Array|\Redis_Cluster|\Predis\Client_Interface $redis, array $options = [])
    {
        if ($diff = array_diff(array_keys($options), ['prefix', 'ttl'])) {
            throw new \InvalidArgumentException(\sprintf('The following options are not supported "%s".', implode(', ', $diff)));
        }
        $this->prefix = $options['prefix'] ?? 'sf_s';
        $this->ttl = $options['ttl'] ?? null;
    }
    protected function do_read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string
    {
        return $this->redis->get($this->prefix . $session_id) ?: '';
    }
    protected function do_write(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        $ttl = ($this->ttl instanceof \Closure ? ($this->ttl)() : $this->ttl) ?? \ini_get('session.gc_maxlifetime');
        $result = $this->redis->set_ex($this->prefix . $session_id, (int) $ttl, $data);
        return $result && !$result instanceof Error_Interface;
    }
    protected function do_destroy(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        static $unlink = true;
        if ($unlink) {
            try {
                $unlink = false !== $this->redis->unlink($this->prefix . $session_id);
            } catch (\Throwable) {
                $unlink = false;
            }
        }
        if (!$unlink) {
            $this->redis->del($this->prefix . $session_id);
        }
        return true;
    }
    #[\Return_Type_Will_Change]
    public function close(): bool
    {
        return true;
    }
    public function gc(int $maxlifetime): int|false
    {
        return 0;
    }
    public function update_timestamp(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        $ttl = ($this->ttl instanceof \Closure ? ($this->ttl)() : $this->ttl) ?? \ini_get('session.gc_maxlifetime');
        return $this->redis->expire($this->prefix . $session_id, (int) $ttl);
    }
}
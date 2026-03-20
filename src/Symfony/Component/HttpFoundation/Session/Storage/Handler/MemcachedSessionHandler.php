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
 * Memcached based session storage handler based on the Memcached class
 * provided by the PHP memcached extension.
 *
 * @see https://php.net/memcached
 *
 * @author Drak <drak@zikula.org>
 */
class Memcached_Session_Handler extends Abstract_Session_Handler
{
    /**
     * Time to live in seconds.
     */
    private readonly int|\Closure|null $ttl;
    /**
     * Key prefix for shared environments.
     */
    private readonly string $prefix;
    /**
     * Constructor.
     *
     * List of available options:
     *  * prefix: The prefix to use for the memcached keys in order to avoid collision
     *  * ttl: The time to live in seconds.
     *
     * @throws \InvalidArgumentException When unsupported options are passed
     */
    public function __construct(private readonly \Memcached $memcached, array $options = [])
    {
        if ($diff = array_diff(array_keys($options), ['prefix', 'expiretime', 'ttl'])) {
            throw new \InvalidArgumentException(\sprintf('The following options are not supported "%s".', implode(', ', $diff)));
        }
        $this->ttl = $options['expiretime'] ?? $options['ttl'] ?? null;
        $this->prefix = $options['prefix'] ?? 'sf2s';
    }
    public function close(): bool
    {
        return $this->memcached->quit();
    }
    protected function do_read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string
    {
        return $this->memcached->get($this->prefix . $session_id) ?: '';
    }
    public function update_timestamp(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        $this->memcached->touch($this->prefix . $session_id, $this->get_compatible_ttl());
        return true;
    }
    protected function do_write(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        return $this->memcached->set($this->prefix . $session_id, $data, $this->get_compatible_ttl());
    }
    private function get_compatible_ttl(): int
    {
        $ttl = ($this->ttl instanceof \Closure ? ($this->ttl)() : $this->ttl) ?? \ini_get('session.gc_maxlifetime');
        // If the relative TTL that is used exceeds 30 days, memcached will treat the value as Unix time.
        // We have to convert it to an absolute Unix time at this point, to make sure the TTL is correct.
        if ($ttl > 60 * 60 * 24 * 30) {
            $ttl += time();
        }
        return $ttl;
    }
    protected function do_destroy(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        $result = $this->memcached->delete($this->prefix . $session_id);
        return $result || \Memcached::RES_NOTFOUND == $this->memcached->get_result_code();
    }
    public function gc(int $maxlifetime): int|false
    {
        // not required here because memcached will auto expire the records anyhow.
        return 0;
    }
    /**
     * Return a Memcached instance.
     */
    protected function get_memcached(): \Memcached
    {
        return $this->memcached;
    }
}
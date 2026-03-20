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
namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Cache_Item;
use Symfony\Component\Cache\Exception\Cache_Exception;
use Symfony\Component\Cache\Marshaller\Marshaller_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Apcu_Adapter extends Abstract_Adapter
{
    /**
     * @throws CacheException if APCu is not enabled
     */
    public function __construct(string $namespace = '', int $default_lifetime = 0, ?string $version = null, private readonly ?Marshaller_Interface $marshaller = null)
    {
        if (!static::is_supported()) {
            throw new Cache_Exception('APCu is not enabled.');
        }
        if ('cli' === \PHP_SAPI) {
            ini_set('apc.use_request_time', 0);
        }
        parent::__construct($namespace, $default_lifetime);
        if (null !== $version) {
            Cache_Item::validate_key($version);
            if (!apcu_exists($version . '@' . $namespace)) {
                $this->do_clear($namespace);
                apcu_add($version . '@' . $namespace, null);
            }
        }
    }
    public static function is_supported(): bool
    {
        return \function_exists('apcu_fetch') && filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOL);
    }
    protected function do_fetch(array $ids): iterable
    {
        $unserialize_callback_handler = ini_set('unserialize_callback_func', self::class . '::handleUnserializeCallback');
        try {
            $values = [];
            foreach (apcu_fetch($ids, $ok) ?: [] as $k => $v) {
                if (null !== $v || $ok) {
                    $values[$k] = null !== $this->marshaller ? $this->marshaller->unmarshall($v) : $v;
                }
            }
            return $values;
        } catch (\Error $e) {
            throw new \ErrorException($e->get_message(), $e->get_code(), \E_ERROR, $e->get_file(), $e->get_line());
        } finally {
            ini_set('unserialize_callback_func', $unserialize_callback_handler);
        }
    }
    protected function do_have(string $id): bool
    {
        return apcu_exists($id);
    }
    protected function do_clear(string $namespace): bool
    {
        return isset($namespace[0]) && class_exists(\Apcu_Iterator::class, false) && ('cli' !== \PHP_SAPI || filter_var(\ini_get('apc.enable_cli'), \FILTER_VALIDATE_BOOL)) ? apcu_delete(new \Apcu_Iterator(\sprintf('/^%s/', preg_quote($namespace, '/')), \APC_ITER_KEY)) : apcu_clear_cache();
    }
    protected function do_delete(array $ids): bool
    {
        foreach ($ids as $id) {
            apcu_delete($id);
        }
        return true;
    }
    protected function do_save(array $values, int $lifetime): array|bool
    {
        if (null !== $this->marshaller && !$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }
        if (false === $failures = apcu_store($values, null, $lifetime)) {
            $failures = $values;
        }
        return array_keys($failures);
    }
}
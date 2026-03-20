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
namespace Symfony\Bundle\Framework_Bundle\Cache_Warmer;

use Symfony\Component\Cache\Adapter\Array_Adapter;
use Symfony\Component\Cache\Adapter\Null_Adapter;
use Symfony\Component\Cache\Adapter\Php_Array_Adapter;
use Symfony\Component\Config\Resource\Class_Existence_Resource;
use Symfony\Component\Http_Kernel\Cache_Warmer\Cache_Warmer_Interface;
abstract class Abstract_Php_File_Cache_Warmer implements Cache_Warmer_Interface
{
    /**
     * @param string $phpArrayFile The PHP file where metadata are cached
     */
    public function __construct(private readonly string $php_array_file)
    {
    }
    public function is_optional(): bool
    {
        return true;
    }
    public function warm_up(string $cache_dir, ?string $build_dir = null): array
    {
        $array_adapter = new Array_Adapter();
        spl_autoload_register([Class_Existence_Resource::class, 'throwOnRequiredClass']);
        try {
            if (!$this->do_warm_up($cache_dir, $array_adapter, $build_dir)) {
                return [];
            }
        } finally {
            spl_autoload_unregister([Class_Existence_Resource::class, 'throwOnRequiredClass']);
        }
        // the ArrayAdapter stores the values serialized
        // to avoid mutation of the data after it was written to the cache
        // so here we un-serialize the values first
        $values = array_map(static fn($val): mixed => null !== $val ? unserialize($val) : null, $array_adapter->get_values());
        return $this->warm_up_php_array_adapter(new Php_Array_Adapter($this->php_array_file, new Null_Adapter()), $values);
    }
    /**
     * @return string[] A list of classes to preload on PHP 7.4+
     */
    protected function warm_up_php_array_adapter(Php_Array_Adapter $php_array_adapter, array $values): array
    {
        return $php_array_adapter->warm_up($values);
    }
    /**
     * @internal
     */
    final protected function ignore_autoload_exception(string $class, \Exception $exception): void
    {
        try {
            Class_Existence_Resource::throw_on_required_class($class, $exception);
        } catch (\Reflection_Exception) {
        }
    }
    /**
     * @return bool false if there is nothing to warm-up
     */
    abstract protected function do_warm_up(string $cache_dir, Array_Adapter $array_adapter, ?string $build_dir = null): bool;
}
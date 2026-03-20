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
use Symfony\Component\Cache\Adapter\Php_Array_Adapter;
use Symfony\Component\Validator\Mapping\Factory\Lazy_Loading_Metadata_Factory;
use Symfony\Component\Validator\Mapping\Loader\Attribute_Loader;
use Symfony\Component\Validator\Mapping\Loader\Loader_Chain;
use Symfony\Component\Validator\Mapping\Loader\Loader_Interface;
use Symfony\Component\Validator\Mapping\Loader\Xml_File_Loader;
use Symfony\Component\Validator\Mapping\Loader\Yaml_File_Loader;
use Symfony\Component\Validator\Validator_Builder;
/**
 * Warms up validator metadata.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class Validator_Cache_Warmer extends Abstract_Php_File_Cache_Warmer
{
    /**
     * @param string $phpArrayFile The PHP file where metadata are cached
     */
    public function __construct(private readonly Validator_Builder $validator_builder, string $php_array_file)
    {
        parent::__construct($php_array_file);
    }
    protected function do_warm_up(string $cache_dir, Array_Adapter $array_adapter, ?string $build_dir = null): bool
    {
        if (!$build_dir) {
            return false;
        }
        $loaders = $this->validator_builder->get_loaders();
        $metadata_factory = new Lazy_Loading_Metadata_Factory(new Loader_Chain($loaders), $array_adapter);
        foreach ($this->extract_supported_loaders($loaders) as $loader) {
            foreach ($loader->get_mapped_classes() as $mapped_class) {
                try {
                    if ($metadata_factory->has_metadata_for($mapped_class)) {
                        $metadata_factory->get_metadata_for($mapped_class);
                    }
                } catch (\Exception $e) {
                    $this->ignore_autoload_exception($mapped_class, $e);
                }
            }
        }
        return true;
    }
    /**
     * @return string[] A list of classes to preload on PHP 7.4+
     */
    protected function warm_up_php_array_adapter(Php_Array_Adapter $php_array_adapter, array $values): array
    {
        // make sure we don't cache null values
        $values = array_filter($values, static fn($val): bool => null !== $val);
        return parent::warm_up_php_array_adapter($php_array_adapter, $values);
    }
    /**
     * @param LoaderInterface[] $loaders
     *
     * @return list<XmlFileLoader|YamlFileLoader|AttributeLoader>
     */
    private function extract_supported_loaders(array $loaders): array
    {
        $supported_loaders = [];
        foreach ($loaders as $loader) {
            if (method_exists($loader, 'getMappedClasses')) {
                $supported_loaders[] = $loader;
            } elseif ($loader instanceof Loader_Chain) {
                $supported_loaders = array_merge($supported_loaders, $this->extract_supported_loaders($loader->get_loaders()));
            }
        }
        return $supported_loaders;
    }
}
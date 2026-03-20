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
use Symfony\Component\Serializer\Mapping\Factory\Cache_Class_Metadata_Factory;
use Symfony\Component\Serializer\Mapping\Factory\Class_Metadata_Factory;
use Symfony\Component\Serializer\Mapping\Loader\Attribute_Loader;
use Symfony\Component\Serializer\Mapping\Loader\Loader_Chain;
use Symfony\Component\Serializer\Mapping\Loader\Loader_Interface;
use Symfony\Component\Serializer\Mapping\Loader\Xml_File_Loader;
use Symfony\Component\Serializer\Mapping\Loader\Yaml_File_Loader;
/**
 * Warms up serializer metadata.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class Serializer_Cache_Warmer extends Abstract_Php_File_Cache_Warmer
{
    /**
     * @param LoaderInterface[] $loaders      The serializer metadata loaders
     * @param string            $phpArrayFile The PHP file where metadata are cached
     */
    public function __construct(private readonly array $loaders, string $php_array_file)
    {
        parent::__construct($php_array_file);
    }
    protected function do_warm_up(string $cache_dir, Array_Adapter $array_adapter, ?string $build_dir = null): bool
    {
        if (!$build_dir) {
            return false;
        }
        if (!$this->loaders) {
            return true;
        }
        $metadata_factory = new Cache_Class_Metadata_Factory(new Class_Metadata_Factory(new Loader_Chain($this->loaders)), $array_adapter);
        foreach ($this->extract_supported_loaders($this->loaders) as $loader) {
            foreach ($loader->get_mapped_classes() as $mapped_class) {
                try {
                    $metadata_factory->get_metadata_for($mapped_class);
                } catch (\Exception $e) {
                    $this->ignore_autoload_exception($mapped_class, $e);
                }
            }
        }
        return true;
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
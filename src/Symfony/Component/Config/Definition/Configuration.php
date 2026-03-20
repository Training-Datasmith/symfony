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
namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Builder\Tree_Builder;
use Symfony\Component\Config\Definition\Configurator\Definition_Configurator;
use Symfony\Component\Config\Definition\Loader\Definition_File_Loader;
use Symfony\Component\Config\File_Locator;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @final
 */
class Configuration implements Configuration_Interface
{
    public function __construct(private readonly Configurable_Interface $subject, private readonly ?Container_Builder $container, private readonly string $alias)
    {
    }
    /**
     * @return TreeBuilder<'array'>
     */
    public function get_config_tree_builder(): Tree_Builder
    {
        $tree_builder = new Tree_Builder($this->alias, 'array');
        $file = (new \Reflection_Object($this->subject))->get_file_name();
        $loader = new Definition_File_Loader($tree_builder, new File_Locator(\dirname($file)), $this->container);
        $configurator = new Definition_Configurator($tree_builder, $loader, $file, $file);
        $this->subject->configure($configurator);
        return $tree_builder;
    }
}
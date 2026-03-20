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
namespace Symfony\Bundle\Web_Profiler_Bundle\Dependency_Injection;

use Symfony\Component\Config\Definition\Builder\Tree_Builder;
use Symfony\Component\Config\Definition\Configuration_Interface;
/**
 * This class contains the configuration information for the bundle.
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Configuration implements Configuration_Interface
{
    /**
     * Generates the configuration tree builder.
     */
    public function get_config_tree_builder(): Tree_Builder
    {
        $tree_builder = new Tree_Builder('web_profiler');
        $tree_builder->get_root_node()->doc_url('https://symfony.com/doc/{version:major}.{version:minor}/reference/configuration/web_profiler.html', 'symfony/web-profiler-bundle')->children()->array_node('toolbar')->info('Profiler toolbar configuration')->can_be_enabled()->children()->boolean_node('ajax_replace')->default_false()->info('Replace toolbar on AJAX requests')->end()->end()->end()->boolean_node('intercept_redirects')->default_false()->end()->scalar_node('excluded_ajax_paths')->default_value('^/((index|app(_[\w]+)?)\.php/)?_wdt')->end()->end();
        return $tree_builder;
    }
}
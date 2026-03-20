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
namespace Symfony\Bundle\Debug_Bundle\Dependency_Injection;

use Symfony\Component\Config\Definition\Builder\Tree_Builder;
use Symfony\Component\Config\Definition\Configuration_Interface;
/**
 * DebugExtension configuration structure.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Configuration implements Configuration_Interface
{
    public function get_config_tree_builder(): Tree_Builder
    {
        $tree_builder = new Tree_Builder('debug');
        $root_node = $tree_builder->get_root_node();
        $root_node->doc_url('https://symfony.com/doc/{version:major}.{version:minor}/reference/configuration/debug.html', 'symfony/debug-bundle')->children()->integer_node('max_items')->info('Max number of displayed items past the first level, -1 means no limit.')->min(-1)->default_value(2500)->end()->integer_node('min_depth')->info('Minimum tree depth to clone all the items, 1 is default.')->min(0)->default_value(1)->end()->integer_node('max_string_length')->info('Max length of displayed strings, -1 means no limit.')->min(-1)->default_value(-1)->end()->scalar_node('dump_destination')->info('A stream URL where dumps should be written to.')->example('php://stderr, or tcp://%env(VAR_DUMPER_SERVER)% when using the "server:dump" command')->default_null()->end()->enum_node('theme')->info('Changes the color of the dump() output when rendered directly on the templating. "dark" (default) or "light".')->example('dark')->values(['dark', 'light'])->default_value('dark')->end();
        return $tree_builder;
    }
}
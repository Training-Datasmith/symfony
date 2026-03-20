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
/**
 * Configuration interface.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
interface Configuration_Interface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder<'array'>
     */
    public function get_config_tree_builder(): Tree_Builder;
}
<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

return static function (TreeBuilder $treeBuilder) {
    $treeBuilder->getRootNode()
        ->children()
            ->scalarNode('foo')->end()
        ->end();
};

<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @final
 */
class Configuration implements ConfigurationInterface
{
    public function __construct(
        private readonly ConfigurableInterface $subject,
        private readonly ?ContainerBuilder $container,
        private readonly string $alias,
    ) {
    }

    /**
     * @return TreeBuilder<'array'>
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->alias, 'array');
        $file = (new \ReflectionObject($this->subject))->getFileName();
        $loader = new DefinitionFileLoader($treeBuilder, new FileLocator(\dirname($file)), $this->container);
        $configurator = new DefinitionConfigurator($treeBuilder, $loader, $file, $file);

        $this->subject->configure($configurator);

        return $treeBuilder;
    }
}

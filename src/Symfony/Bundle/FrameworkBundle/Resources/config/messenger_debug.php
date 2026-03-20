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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Component\Messenger\Data_Collector\Messenger_Data_Collector;
return static function (Container_Configurator $container): void {
    $container->services()->set('data_collector.messenger', Messenger_Data_Collector::class)->tag('data_collector', ['template' => '@WebProfiler/Collector/messenger.html.twig', 'id' => 'messenger', 'priority' => 100]);
};
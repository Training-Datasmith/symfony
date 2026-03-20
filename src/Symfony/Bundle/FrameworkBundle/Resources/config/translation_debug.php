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

use Symfony\Component\Translation\Data_Collector\Translation_Data_Collector;
use Symfony\Component\Translation\Data_Collector_Translator;
return static function (Container_Configurator $container): void {
    $container->services()->set('translator.data_collector', Data_Collector_Translator::class)->args([service('translator.data_collector.inner')])->tag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore'])->set('data_collector.translation', Translation_Data_Collector::class)->args([service('translator.data_collector')])->tag('data_collector', ['template' => '@WebProfiler/Collector/translation.html.twig', 'id' => 'translation', 'priority' => 275]);
};
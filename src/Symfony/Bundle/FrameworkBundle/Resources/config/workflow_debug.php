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

use Symfony\Component\Workflow\Data_Collector\Workflow_Data_Collector;
return static function (Container_Configurator $container): void {
    $container->services()->set('data_collector.workflow', Workflow_Data_Collector::class)->tag('data_collector', ['template' => '@WebProfiler/Collector/workflow.html.twig', 'id' => 'workflow'])->args([tagged_iterator('workflow', 'name'), service('event_dispatcher'), service('debug.file_link_formatter')]);
};
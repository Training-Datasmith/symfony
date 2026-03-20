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

use Symfony\Component\Notifier\Data_Collector\Notification_Data_Collector;
return static function (Container_Configurator $container): void {
    $container->services()->set('notifier.data_collector', Notification_Data_Collector::class)->args([service('notifier.notification_logger_listener')])->tag('data_collector', ['template' => '@WebProfiler/Collector/notifier.html.twig', 'id' => 'notifier']);
};
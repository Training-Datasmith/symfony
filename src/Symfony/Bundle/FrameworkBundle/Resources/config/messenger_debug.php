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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('data_collector.messenger', MessengerDataCollector::class)
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/messenger.html.twig',
                'id' => 'messenger',
                'priority' => 100,
            ])
    ;
};

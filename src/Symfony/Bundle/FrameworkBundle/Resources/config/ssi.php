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

use Symfony\Component\HttpKernel\EventListener\SurrogateListener;
use Symfony\Component\HttpKernel\HttpCache\Ssi;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('ssi', Ssi::class)

        ->set('ssi_listener', SurrogateListener::class)
            ->args([service('ssi')->ignoreOnInvalid()])
            ->tag('kernel.event_subscriber')
    ;
};

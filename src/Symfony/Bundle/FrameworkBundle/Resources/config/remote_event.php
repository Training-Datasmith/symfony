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

use Symfony\Component\Remote_Event\Messenger\Consume_Remote_Event_Handler;
return static function (Container_Configurator $container): void {
    $container->services()->set('remote_event.messenger.handler', Consume_Remote_Event_Handler::class)->args([tagged_locator('remote_event.consumer', 'consumer')])->tag('messenger.message_handler');
};
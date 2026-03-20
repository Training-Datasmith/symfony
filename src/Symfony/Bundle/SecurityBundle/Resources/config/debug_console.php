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

use Symfony\Bundle\Security_Bundle\Command\Debug_Firewall_Command;
return static function (Container_Configurator $container): void {
    $container->services()->set('security.command.debug_firewall', Debug_Firewall_Command::class)->args([param('security.firewalls'), service('security.firewall.context_locator'), tagged_locator('event_dispatcher.dispatcher', 'name'), [], false])->tag('console.command', ['command' => 'debug:firewall']);
};
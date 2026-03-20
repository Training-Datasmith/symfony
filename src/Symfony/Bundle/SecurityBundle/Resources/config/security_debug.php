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

use Symfony\Bundle\Security_Bundle\Debug\Traceable_Firewall_Listener;
use Symfony\Bundle\Security_Bundle\Event_Listener\Vote_Listener;
use Symfony\Component\Security\Core\Authorization\Traceable_Access_Decision_Manager;
return static function (Container_Configurator $container): void {
    $container->services()->set('debug.security.access.decision_manager', Traceable_Access_Decision_Manager::class)->decorate('security.access.decision_manager')->args([service('debug.security.access.decision_manager.inner')])->tag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore'])->set('debug.security.voter.vote_listener', Vote_Listener::class)->args([service('debug.security.access.decision_manager')])->tag('kernel.event_subscriber')->set('debug.security.firewall', Traceable_Firewall_Listener::class)->args([service('security.firewall.map'), service('event_dispatcher'), service('security.logout_url_generator')])->tag('kernel.event_subscriber')->tag('kernel.reset', ['method' => 'reset'])->alias('security.firewall', 'debug.security.firewall');
};
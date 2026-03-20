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

use Symfony\Bundle\Security_Bundle\Data_Collector\Security_Data_Collector;
return static function (Container_Configurator $container): void {
    $container->services()->set('data_collector.security', Security_Data_Collector::class)->args([service('security.untracked_token_storage'), service('security.role_hierarchy'), service('security.logout_url_generator'), service('security.access.decision_manager'), service('security.firewall.map'), service('debug.security.firewall')->null_on_invalid()])->tag('data_collector', ['template' => '@Security/Collector/security.html.twig', 'id' => 'security', 'priority' => 270]);
};
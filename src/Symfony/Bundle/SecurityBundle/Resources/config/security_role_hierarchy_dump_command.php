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

use Symfony\Bundle\Security_Bundle\Command\Security_Role_Hierarchy_Dump_Command;
return static function (Container_Configurator $container): void {
    $container->services()->set('security.command.role_hierarchy_dump', Security_Role_Hierarchy_Dump_Command::class)->args([service('security.role_hierarchy')])->tag('console.command');
};
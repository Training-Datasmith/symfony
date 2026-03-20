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

use Symfony\Component\Password_Hasher\Command\User_Password_Hash_Command;
return static function (Container_Configurator $container): void {
    $container->services()->set('security.command.user_password_hash', User_Password_Hash_Command::class)->args([service('security.password_hasher_factory'), abstract_arg('list of user classes')])->tag('console.command');
};
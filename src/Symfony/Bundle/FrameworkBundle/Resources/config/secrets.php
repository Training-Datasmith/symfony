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

use Symfony\Bundle\Framework_Bundle\Secrets\Dotenv_Vault;
use Symfony\Bundle\Framework_Bundle\Secrets\Sodium_Vault;
use Symfony\Component\Dependency_Injection\Static_Env_Var_Loader;
return static function (Container_Configurator $container): void {
    $container->services()->set('secrets.vault', Sodium_Vault::class)->args([abstract_arg('Secret dir, set in FrameworkExtension'), service('secrets.decryption_key')->ignore_on_invalid(), abstract_arg('Secret env var, set in FrameworkExtension')])->set('secrets.env_var_loader', Static_Env_Var_Loader::class)->args([service('secrets.vault')])->tag('container.env_var_loader')->set('secrets.decryption_key')->parent('container.env')->args([abstract_arg('Decryption env var, set in FrameworkExtension')])->set('secrets.local_vault', Dotenv_Vault::class)->args([abstract_arg('.env file path, set in FrameworkExtension')]);
};
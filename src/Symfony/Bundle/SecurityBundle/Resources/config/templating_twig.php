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

use Symfony\Bridge\Twig\Extension\Logout_Url_Extension;
use Symfony\Bridge\Twig\Extension\Security_Extension;
return static function (Container_Configurator $container): void {
    $container->services()->set('twig.extension.logout_url', Logout_Url_Extension::class)->args([service('security.logout_url_generator')])->tag('twig.extension')->set('twig.extension.security', Security_Extension::class)->args([service('security.authorization_checker')->ignore_on_invalid(), service('security.impersonate_url_generator')->ignore_on_invalid()])->tag('twig.extension');
};
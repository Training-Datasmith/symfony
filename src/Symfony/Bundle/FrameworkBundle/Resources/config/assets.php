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

use Symfony\Component\Asset\Context\Request_Stack_Context;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\Path_Package;
use Symfony\Component\Asset\Url_Package;
use Symfony\Component\Asset\Version_Strategy\Empty_Version_Strategy;
use Symfony\Component\Asset\Version_Strategy\Json_Manifest_Version_Strategy;
use Symfony\Component\Asset\Version_Strategy\Static_Version_Strategy;
return static function (Container_Configurator $container): void {
    $container->parameters()->set('asset.request_context.base_path', null)->set('asset.request_context.secure', null);
    $container->services()->set('assets.packages', Packages::class)->args([service('assets._default_package'), tagged_iterator('assets.package', 'package')])->alias(Packages::class, 'assets.packages')->set('assets.empty_package', Package::class)->args([service('assets.empty_version_strategy')])->alias('assets._default_package', 'assets.empty_package')->set('assets.context', Request_Stack_Context::class)->args([service('request_stack'), param('asset.request_context.base_path'), param('asset.request_context.secure')])->set('assets.path_package', Path_Package::class)->abstract()->args([abstract_arg('base path'), abstract_arg('version strategy'), service('assets.context')])->set('assets.url_package', Url_Package::class)->abstract()->args([abstract_arg('base URLs'), abstract_arg('version strategy'), service('assets.context')])->set('assets.static_version_strategy', Static_Version_Strategy::class)->abstract()->args([abstract_arg('version'), abstract_arg('format')])->set('assets.empty_version_strategy', Empty_Version_Strategy::class)->set('assets.json_manifest_version_strategy', Json_Manifest_Version_Strategy::class)->abstract()->args([abstract_arg('manifest path'), service('http_client')->null_on_invalid(), false]);
};
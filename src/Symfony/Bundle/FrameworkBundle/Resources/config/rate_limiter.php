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

use Symfony\Component\Rate_Limiter\Rate_Limiter_Factory;
return static function (Container_Configurator $container): void {
    $container->services()->set('cache.rate_limiter')->parent('cache.app')->tag('cache.pool')->set('limiter', Rate_Limiter_Factory::class)->abstract()->args([abstract_arg('config'), abstract_arg('storage'), null]);
};
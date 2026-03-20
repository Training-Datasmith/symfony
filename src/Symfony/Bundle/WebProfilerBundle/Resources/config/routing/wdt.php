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
use Symfony\Component\Routing\Loader\Configurator\Routing_Configurator;
return static function (Routing_Configurator $routes): void {
    $routes->add('_wdt_stylesheet', '/styles')->controller('web_profiler.controller.profiler::toolbarStylesheetAction');
    $routes->add('_wdt', '/{token}')->controller('web_profiler.controller.profiler::toolbarAction');
};
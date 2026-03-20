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

use Symfony\Component\Html_Sanitizer\Html_Sanitizer;
use Symfony\Component\Html_Sanitizer\Html_Sanitizer_Config;
use Symfony\Component\Html_Sanitizer\Html_Sanitizer_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('html_sanitizer.config.default', Html_Sanitizer_Config::class)->call('allowSafeElements', [], true)->set('html_sanitizer.sanitizer.default', Html_Sanitizer::class)->args([service('html_sanitizer.config.default')])->tag('html_sanitizer', ['sanitizer' => 'default'])->alias('html_sanitizer', 'html_sanitizer.sanitizer.default')->alias(Html_Sanitizer_Interface::class, 'html_sanitizer');
};
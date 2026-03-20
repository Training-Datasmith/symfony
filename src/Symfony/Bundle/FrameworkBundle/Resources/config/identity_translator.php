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

use Symfony\Component\Translation\Identity_Translator;
use Symfony\Contracts\Translation\Translator_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('translator', Identity_Translator::class)->alias(Translator_Interface::class, 'translator')->set('identity_translator', Identity_Translator::class);
};
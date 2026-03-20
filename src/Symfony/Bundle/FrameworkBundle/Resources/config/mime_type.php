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

use Symfony\Component\Mime\Mime_Type_Guesser_Interface;
use Symfony\Component\Mime\Mime_Types;
use Symfony\Component\Mime\Mime_Types_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('mime_types', Mime_Types::class)->call('setDefault', [service('mime_types')])->alias(Mime_Types_Interface::class, 'mime_types')->alias(Mime_Type_Guesser_Interface::class, 'mime_types');
};
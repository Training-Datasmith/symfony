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

use Symfony\Component\Process\Messenger\Run_Process_Message_Handler;
return static function (Container_Configurator $container): void {
    $container->services()->set('process.messenger.process_message_handler', Run_Process_Message_Handler::class)->tag('messenger.message_handler', ['sign' => true]);
};
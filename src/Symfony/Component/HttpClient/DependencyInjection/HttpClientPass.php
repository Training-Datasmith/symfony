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
namespace Symfony\Component\Http_Client\Dependency_Injection;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Dependency_Injection\Reference;
use Symfony\Component\Http_Client\Traceable_Http_Client;
final class Http_Client_Pass implements Compiler_Pass_Interface
{
    public function process(Container_Builder $container): void
    {
        if (!$container->has_definition('data_collector.http_client')) {
            return;
        }
        foreach ($container->find_tagged_service_ids('http_client.client') as $id => $tags) {
            $container->register('.debug.' . $id, Traceable_Http_Client::class)->set_decorated_service($id, null, 100)->set_arguments([new Reference('.inner'), new Reference('debug.stopwatch', Container_Interface::IGNORE_ON_INVALID_REFERENCE), new Reference('profiler.is_disabled_state_checker', Container_Interface::IGNORE_ON_INVALID_REFERENCE)])->add_tag('kernel.reset', ['method' => 'reset']);
            $container->get_definition('data_collector.http_client')->add_method_call('registerClient', [$id, new Reference('.debug.' . $id)]);
        }
    }
}
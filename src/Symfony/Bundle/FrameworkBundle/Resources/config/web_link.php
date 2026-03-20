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

use Symfony\Component\Web_Link\Event_Listener\Add_Link_Header_Listener;
use Symfony\Component\Web_Link\Http_Header_Parser;
use Symfony\Component\Web_Link\Http_Header_Serializer;
return static function (Container_Configurator $container): void {
    $container->services()->set('web_link.http_header_serializer', Http_Header_Serializer::class)->alias(Http_Header_Serializer::class, 'web_link.http_header_serializer')->set('web_link.http_header_parser', Http_Header_Parser::class)->alias(Http_Header_Parser::class, 'web_link.http_header_parser')->set('web_link.add_link_header_listener', Add_Link_Header_Listener::class)->args([service('web_link.http_header_serializer')])->tag('kernel.event_subscriber');
};
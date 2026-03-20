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
namespace Symfony\Component\Http_Kernel\Event_Listener;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Kernel\Event\Response_Event;
use Symfony\Component\Http_Kernel\Kernel_Events;
/**
 * Ensures that the application is not indexed by search engines.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class Disallow_Robots_Indexing_Listener implements Event_Subscriber_Interface
{
    private const HEADER_NAME = 'X-Robots-Tag';
    public function on_response(Response_Event $event): void
    {
        if (!$event->get_response()->headers->has(static::HEADER_NAME)) {
            $event->get_response()->headers->set(static::HEADER_NAME, 'noindex');
        }
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::RESPONSE => ['onResponse', -255]];
    }
}
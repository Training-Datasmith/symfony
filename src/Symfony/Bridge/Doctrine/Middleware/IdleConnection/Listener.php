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
namespace Symfony\Bridge\Doctrine\Middleware\Idle_Connection;

use Symfony\Component\Dependency_Injection\Container_Interface;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Http_Kernel\Event\Request_Event;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
use Symfony\Component\Http_Kernel\Kernel_Events;
final readonly class Listener implements Event_Subscriber_Interface
{
    /**
     * @param \ArrayObject<string, int> $connectionExpiries
     */
    public function __construct(private \ArrayObject $connection_expiries, private Container_Interface $container)
    {
    }
    public function on_kernel_request(Request_Event $event): void
    {
        if (Http_Kernel_Interface::MAIN_REQUEST !== $event->get_request_type()) {
            return;
        }
        $timestamp = time();
        foreach ($this->connection_expiries as $name => $expiry) {
            if ($timestamp >= $expiry) {
                // unset before so that we won't retry in case of any failure
                $this->connection_expiries->offsetUnset($name);
                try {
                    $connection = $this->container->get("doctrine.dbal.{$name}_connection");
                    $connection->close();
                } catch (\Exception) {
                    // ignore exceptions to remain fail-safe
                }
            }
        }
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::REQUEST => ['onKernelRequest', 192]];
    }
}
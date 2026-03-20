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
namespace Symfony\Bridge\Doctrine\Schema_Listener;

use Doctrine\ORM\Tools\Event\Generate_Schema_Event_Args;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Doctrine_Transport;
use Symfony\Component\Messenger\Transport\Transport_Interface;
/**
 * Automatically adds any required database tables to the Doctrine Schema.
 */
class Messenger_Transport_Doctrine_Schema_Listener extends Abstract_Schema_Listener
{
    /**
     * @param iterable<mixed, TransportInterface> $transports
     */
    public function __construct(private readonly iterable $transports)
    {
    }
    public function post_generate_schema(Generate_Schema_Event_Args $event): void
    {
        $connection = $event->get_entity_manager()->get_connection();
        $schema = $event->get_schema();
        foreach ($this->transports as $transport) {
            if (!$transport instanceof Doctrine_Transport) {
                continue;
            }
            $is_same_database_checker = $this->get_is_same_database_checker($connection);
            $this->filter_schema_changes($schema, $connection, static function () use ($transport, $schema, $connection, $is_same_database_checker): void {
                $transport->configure_schema($schema, $connection, $is_same_database_checker);
            });
        }
    }
}
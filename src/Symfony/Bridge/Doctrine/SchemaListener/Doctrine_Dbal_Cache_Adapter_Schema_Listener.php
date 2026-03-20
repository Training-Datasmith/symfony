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
use Symfony\Component\Cache\Adapter\Doctrine_Dbal_Adapter;
/**
 * Automatically adds the cache table needed for the DoctrineDbalAdapter of
 * the Cache component.
 */
class Doctrine_Dbal_Cache_Adapter_Schema_Listener extends Abstract_Schema_Listener
{
    /**
     * @param iterable<mixed, DoctrineDbalAdapter> $dbalAdapters
     */
    public function __construct(private readonly iterable $dbal_adapters)
    {
    }
    public function post_generate_schema(Generate_Schema_Event_Args $event): void
    {
        $connection = $event->get_entity_manager()->get_connection();
        $schema = $event->get_schema();
        foreach ($this->dbal_adapters as $dbal_adapter) {
            $is_same_database_checker = $this->get_is_same_database_checker($connection);
            $this->filter_schema_changes($schema, $connection, static function () use ($dbal_adapter, $schema, $connection, $is_same_database_checker): void {
                $dbal_adapter->configure_schema($schema, $connection, $is_same_database_checker);
            });
        }
    }
}
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
use Symfony\Component\Lock\Persisting_Store_Interface;
use Symfony\Component\Lock\Store\Doctrine_Dbal_Store;
final class Lock_Store_Schema_Listener extends Abstract_Schema_Listener
{
    /**
     * @param iterable<mixed, PersistingStoreInterface> $stores
     */
    public function __construct(private readonly iterable $stores)
    {
    }
    public function post_generate_schema(Generate_Schema_Event_Args $event): void
    {
        $connection = $event->get_entity_manager()->get_connection();
        $schema = $event->get_schema();
        foreach ($this->stores as $store) {
            if (!$store instanceof Doctrine_Dbal_Store) {
                continue;
            }
            $is_same_database_checker = $this->get_is_same_database_checker($connection);
            $this->filter_schema_changes($schema, $connection, static function () use ($store, $schema, $is_same_database_checker): void {
                $store->configure_schema($schema, $is_same_database_checker);
            });
        }
    }
}
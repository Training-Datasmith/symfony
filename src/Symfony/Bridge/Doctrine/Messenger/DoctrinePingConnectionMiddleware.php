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
namespace Symfony\Bridge\Doctrine\Messenger;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\Entity_Manager_Interface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\Stack_Interface;
use Symfony\Component\Messenger\Stamp\Consumed_By_Worker_Stamp;
/**
 * Checks whether the connection is still open or reconnects otherwise.
 *
 * @author Fuong <insidestyles@gmail.com>
 */
class Doctrine_Ping_Connection_Middleware extends Abstract_Doctrine_Middleware
{
    protected function handle_for_manager(Entity_Manager_Interface $entity_manager, Envelope $envelope, Stack_Interface $stack): Envelope
    {
        if (null !== $envelope->last(Consumed_By_Worker_Stamp::class)) {
            foreach ($this->get_target_entity_managers($entity_manager) as $name => $target_entity_manager) {
                $this->ping_connection($target_entity_manager, $name);
            }
        }
        return $stack->next()->handle($envelope, $stack);
    }
    /**
     * @return iterable<string|null, EntityManagerInterface>
     */
    private function get_target_entity_managers(Entity_Manager_Interface $entity_manager): iterable
    {
        if (null !== $this->entity_manager_name) {
            yield $this->entity_manager_name => $entity_manager;
            return;
        }
        foreach ($this->manager_registry->get_manager_names() as $name => $service_id) {
            $manager = $this->manager_registry->get_manager($name);
            if ($manager instanceof Entity_Manager_Interface) {
                yield $name => $manager;
            }
        }
    }
    private function ping_connection(Entity_Manager_Interface $entity_manager, ?string $entity_manager_name = null): void
    {
        $connection = $entity_manager->get_connection();
        if (!$connection->is_connected()) {
            return;
        }
        try {
            $this->execute_dummy_sql($connection);
        } catch (Dbal_Exception) {
            $connection->close();
            // Attempt to reestablish the lazy connection by sending another query.
            $this->execute_dummy_sql($connection);
        }
        if (!$entity_manager->is_open()) {
            $this->manager_registry->reset_manager($entity_manager_name ?? $this->entity_manager_name);
        }
    }
    /**
     * @throws DBALException
     */
    private function execute_dummy_sql(Connection $connection): void
    {
        $connection->execute_query($connection->get_database_platform()->get_dummy_select_sql());
    }
}
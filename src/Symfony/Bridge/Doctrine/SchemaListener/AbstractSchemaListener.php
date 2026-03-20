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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\Connection_Exception;
use Doctrine\DBAL\Exception\Database_Object_Exists_Exception;
use Doctrine\DBAL\Exception\Database_Object_Not_Found_Exception;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\Unqualified_Name;
use Doctrine\DBAL\Schema\Named_Object;
use Doctrine\DBAL\Schema\Primary_Key_Constraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\Generate_Schema_Event_Args;
abstract class Abstract_Schema_Listener
{
    abstract public function post_generate_schema(Generate_Schema_Event_Args $event): void;
    protected function filter_schema_changes(Schema $schema, Connection $connection, callable $configurator): void
    {
        $filter = $connection->get_configuration()->get_schema_assets_filter();
        if (null === $filter) {
            $configurator();
            return;
        }
        $get_names = static fn($array): array => array_map(static fn($object) => $object instanceof Named_Object ? $object->get_object_name()->to_string() : $object->get_name(), $array);
        $previous_table_names = $get_names($schema->get_tables());
        $previous_sequence_names = $get_names($schema->get_sequences());
        $configurator();
        foreach (array_diff($get_names($schema->get_tables()), $previous_table_names) as $added_table) {
            if (!$filter($added_table)) {
                $schema->drop_table($added_table);
            }
        }
        foreach (array_diff($get_names($schema->get_sequences()), $previous_sequence_names) as $added_sequence) {
            if (!$filter($added_sequence)) {
                $schema->drop_sequence($added_sequence);
            }
        }
    }
    /**
     * @return \Closure(\Closure(string): mixed): bool
     */
    protected function get_is_same_database_checker(Connection $connection): \Closure
    {
        return static function (\Closure $exec) use ($connection): bool {
            $schema_manager = $connection->create_schema_manager();
            $key = bin2hex(random_bytes(7));
            $table = new Table('_schema_subscriber_check');
            $table->add_column('id', Types::INTEGER)->set_autoincrement(true)->set_notnull(true);
            $table->add_column('random_key', Types::STRING)->set_length(14)->set_not_null(true);
            $table->add_primary_key_constraint(new Primary_Key_Constraint(null, [new Unqualified_Name(Identifier::unquoted('id'))], true));
            try {
                $schema_manager->create_table($table);
            } catch (Database_Object_Exists_Exception) {
            }
            $connection->execute_statement('INSERT INTO _schema_subscriber_check (random_key) VALUES (:key)', ['key' => $key], ['key' => Types::STRING]);
            try {
                $exec(\sprintf('DELETE FROM _schema_subscriber_check WHERE random_key = %s', $connection->get_database_platform()->quote_string_literal($key)));
            } catch (Database_Object_Not_Found_Exception|Connection_Exception|\PDOException) {
            }
            try {
                return !$connection->execute_statement('DELETE FROM _schema_subscriber_check WHERE random_key = :key', ['key' => $key], ['key' => Types::STRING]);
            } finally {
                if (!$connection->execute_query('SELECT count(id) FROM _schema_subscriber_check')->fetch_one()) {
                    try {
                        $schema_manager->drop_table('_schema_subscriber_check');
                    } catch (Database_Object_Not_Found_Exception) {
                    }
                }
            }
        };
    }
}
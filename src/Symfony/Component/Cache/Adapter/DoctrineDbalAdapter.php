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
namespace Symfony\Component\Cache\Adapter;

use Doctrine\DBAL\Array_Parameter_Type;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver_Manager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\Table_Not_Found_Exception;
use Doctrine\DBAL\Parameter_Type;
use Doctrine\DBAL\Platforms\Abstract_My_Sql_Platform;
use Doctrine\DBAL\Platforms\Oracle_Platform;
use Doctrine\DBAL\Platforms\Postgre_Sql_Platform;
use Doctrine\DBAL\Platforms\Sq_Lite_Platform;
use Doctrine\DBAL\Platforms\Sql_Server_Platform;
use Doctrine\DBAL\Schema\Default_Schema_Manager_Factory;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\Unqualified_Name;
use Doctrine\DBAL\Schema\Primary_Key_Constraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\Dsn_Parser;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\Default_Marshaller;
use Symfony\Component\Cache\Marshaller\Marshaller_Interface;
use Symfony\Component\Cache\Pruneable_Interface;
class Doctrine_Dbal_Adapter extends Abstract_Adapter implements Pruneable_Interface
{
    private const MAX_KEY_LENGTH = 255;
    private static int $savepoint_counter = 0;
    private Connection $conn;
    private string $platform_name;
    private string $table = 'cache_items';
    private string $id_col = 'item_id';
    private string $data_col = 'item_data';
    private string $lifetime_col = 'item_lifetime';
    private string $time_col = 'item_time';
    /**
     * You can either pass an existing database Doctrine DBAL Connection or
     * a DSN string that will be used to connect to the database.
     *
     * The cache table is created automatically when possible.
     * Otherwise, use the createTable() method.
     *
     * List of available options:
     *  * db_table: The name of the table [default: cache_items]
     *  * db_id_col: The column where to store the cache id [default: item_id]
     *  * db_data_col: The column where to store the cache data [default: item_data]
     *  * db_lifetime_col: The column where to store the lifetime [default: item_lifetime]
     *  * db_time_col: The column where to store the timestamp [default: item_time]
     *
     * @throws InvalidArgumentException When namespace contains invalid characters
     */
    public function __construct(Connection|string $conn_or_dsn, private readonly string $namespace = '', int $default_lifetime = 0, array $options = [], private readonly ?Marshaller_Interface $marshaller = new Default_Marshaller())
    {
        if (isset($namespace[0]) && preg_match('#[^-+.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(\sprintf('Namespace contains "%s" but only characters in [-+.A-Za-z0-9] are allowed.', $match[0]));
        }
        if ($conn_or_dsn instanceof Connection) {
            $this->conn = $conn_or_dsn;
        } else {
            if (!class_exists(Driver_Manager::class)) {
                throw new InvalidArgumentException('Failed to parse DSN. Try running "composer require doctrine/dbal".');
            }
            $params = (new Dsn_Parser(['db2' => 'ibm_db2', 'mssql' => 'pdo_sqlsrv', 'mysql' => 'pdo_mysql', 'mysql2' => 'pdo_mysql', 'postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql', 'pgsql' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite', 'sqlite3' => 'pdo_sqlite']))->parse($conn_or_dsn);
            $config = new Configuration();
            $config->set_schema_manager_factory(new Default_Schema_Manager_Factory());
            $this->conn = Driver_Manager::get_connection($params, $config);
        }
        $this->max_id_length = self::MAX_KEY_LENGTH;
        $this->table = $options['db_table'] ?? $this->table;
        $this->id_col = $options['db_id_col'] ?? $this->id_col;
        $this->data_col = $options['db_data_col'] ?? $this->data_col;
        $this->lifetime_col = $options['db_lifetime_col'] ?? $this->lifetime_col;
        $this->time_col = $options['db_time_col'] ?? $this->time_col;
        parent::__construct($namespace, $default_lifetime);
    }
    /**
     * Creates the table to store cache items which can be called once for setup.
     *
     * Cache ID are saved in a column of maximum length 255. Cache data is
     * saved in a BLOB.
     *
     * @throws DBALException When the table already exists
     */
    public function create_table(): void
    {
        $schema = new Schema();
        $this->add_table_to_schema($schema);
        foreach ($schema->to_sql($this->conn->get_database_platform()) as $sql) {
            $this->conn->execute_statement($sql);
        }
    }
    public function configure_schema(Schema $schema, Connection $for_connection, \Closure $is_same_database): void
    {
        if ($schema->has_table($this->table)) {
            return;
        }
        if ($for_connection !== $this->conn && !$is_same_database($this->conn->execute_statement(...))) {
            return;
        }
        $this->add_table_to_schema($schema);
    }
    public function prune(): bool
    {
        $delete_sql = "DELETE FROM {$this->table} WHERE {$this->lifetime_col} + {$this->time_col} <= ?";
        $params = [time()];
        $param_types = [Parameter_Type::INTEGER];
        if ('' !== $this->namespace) {
            $delete_sql .= " AND {$this->id_col} LIKE ?";
            $params[] = \sprintf('%s%%', $this->namespace);
            $param_types[] = Parameter_Type::STRING;
        }
        try {
            $this->conn->execute_statement($delete_sql, $params, $param_types);
        } catch (Table_Not_Found_Exception) {
        }
        return true;
    }
    protected function do_fetch(array $ids): iterable
    {
        $now = time();
        $expired = [];
        $sql = "SELECT {$this->id_col}, CASE WHEN {$this->lifetime_col} IS NULL OR {$this->lifetime_col} + {$this->time_col} > ? THEN {$this->data_col} ELSE NULL END FROM {$this->table} WHERE {$this->id_col} IN (?)";
        $result = $this->conn->execute_query($sql, [$now, $ids], [Parameter_Type::INTEGER, Array_Parameter_Type::STRING])->iterate_numeric();
        foreach ($result as $row) {
            if (null === $row[1]) {
                $expired[] = $row[0];
            } else {
                yield $row[0] => $this->marshaller->unmarshall(\is_resource($row[1]) ? stream_get_contents($row[1]) : $row[1]);
            }
        }
        if ($expired) {
            $sql = "DELETE FROM {$this->table} WHERE {$this->lifetime_col} + {$this->time_col} <= ? AND {$this->id_col} IN (?)";
            $this->conn->execute_statement($sql, [$now, $expired], [Parameter_Type::INTEGER, Array_Parameter_Type::STRING]);
        }
    }
    protected function do_have(string $id): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE {$this->id_col} = ? AND ({$this->lifetime_col} IS NULL OR {$this->lifetime_col} + {$this->time_col} > ?)";
        $result = $this->conn->execute_query($sql, [$id, time()], [Parameter_Type::STRING, Parameter_Type::INTEGER]);
        return (bool) $result->fetch_one();
    }
    protected function do_clear(string $namespace): bool
    {
        if ('' === $namespace) {
            $sql = $this->conn->get_database_platform()->get_truncate_table_sql($this->table);
        } else {
            $sql = "DELETE FROM {$this->table} WHERE {$this->id_col} LIKE '{$namespace}%'";
        }
        try {
            $this->conn->execute_statement($sql);
        } catch (Table_Not_Found_Exception) {
        }
        return true;
    }
    protected function do_delete(array $ids): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->id_col} IN (?)";
        try {
            $this->conn->execute_statement($sql, [array_values($ids)], [Array_Parameter_Type::STRING]);
        } catch (Table_Not_Found_Exception) {
        }
        return true;
    }
    protected function do_save(array $values, int $lifetime): array|bool
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }
        if ($this->conn->is_transaction_active() && $this->conn->get_database_platform()->supports_savepoints()) {
            $savepoint = 'cache_save_' . ++self::$savepoint_counter;
            try {
                $this->conn->create_savepoint($savepoint);
                $failed = $this->do_save_inner($values, $lifetime, $failed);
                $this->conn->release_savepoint($savepoint);
                return $failed;
            } catch (\Throwable $e) {
                $this->conn->rollback_savepoint($savepoint);
                throw $e;
            }
        }
        return $this->do_save_inner($values, $lifetime, $failed);
    }
    private function do_save_inner(array $values, int $lifetime, array $failed): array
    {
        $platform_name = $this->get_platform_name();
        $insert_sql = "INSERT INTO {$this->table} ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (?, ?, ?, ?)";
        switch ($platform_name) {
            case 'mysql':
                $sql = $insert_sql . " ON DUPLICATE KEY UPDATE {$this->data_col} = VALUES({$this->data_col}), {$this->lifetime_col} = VALUES({$this->lifetime_col}), {$this->time_col} = VALUES({$this->time_col})";
                break;
            case 'oci':
                // DUAL is Oracle specific dummy table
                $sql = "MERGE INTO {$this->table} USING DUAL ON ({$this->id_col} = ?) " . "WHEN NOT MATCHED THEN INSERT ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (?, ?, ?, ?) " . "WHEN MATCHED THEN UPDATE SET {$this->data_col} = ?, {$this->lifetime_col} = ?, {$this->time_col} = ?";
                break;
            case 'sqlsrv':
                // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                // It also requires HOLDLOCK according to http://weblogs.sqlteam.com/dang/archive/2009/01/31/UPSERT-Race-Condition-With-MERGE.aspx
                $sql = "MERGE INTO {$this->table} WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON ({$this->id_col} = ?) " . "WHEN NOT MATCHED THEN INSERT ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (?, ?, ?, ?) " . "WHEN MATCHED THEN UPDATE SET {$this->data_col} = ?, {$this->lifetime_col} = ?, {$this->time_col} = ?;";
                break;
            case 'sqlite':
                $sql = 'INSERT OR REPLACE' . substr($insert_sql, 6);
                break;
            case 'pgsql':
                $sql = $insert_sql . " ON CONFLICT ({$this->id_col}) DO UPDATE SET ({$this->data_col}, {$this->lifetime_col}, {$this->time_col}) = (EXCLUDED.{$this->data_col}, EXCLUDED.{$this->lifetime_col}, EXCLUDED.{$this->time_col})";
                break;
            default:
                $platform_name = null;
                $sql = "UPDATE {$this->table} SET {$this->data_col} = ?, {$this->lifetime_col} = ?, {$this->time_col} = ? WHERE {$this->id_col} = ?";
                break;
        }
        $now = time();
        $lifetime = $lifetime ?: null;
        try {
            $stmt = $this->conn->prepare($sql);
        } catch (Table_Not_Found_Exception) {
            if (!$this->conn->is_transaction_active() || \in_array($platform_name, ['pgsql', 'sqlite', 'sqlsrv'], true)) {
                $this->create_table();
            }
            $stmt = $this->conn->prepare($sql);
        }
        if ('sqlsrv' === $platform_name || 'oci' === $platform_name) {
            $bind = static function ($id, $data) use ($stmt): void {
                $stmt->bind_value(1, $id);
                $stmt->bind_value(2, $id);
                $stmt->bind_value(3, $data, Parameter_Type::LARGE_OBJECT);
                $stmt->bind_value(6, $data, Parameter_Type::LARGE_OBJECT);
            };
            $stmt->bind_value(4, $lifetime, Parameter_Type::INTEGER);
            $stmt->bind_value(5, $now, Parameter_Type::INTEGER);
            $stmt->bind_value(7, $lifetime, Parameter_Type::INTEGER);
            $stmt->bind_value(8, $now, Parameter_Type::INTEGER);
        } elseif (null !== $platform_name) {
            $bind = static function ($id, $data) use ($stmt): void {
                $stmt->bind_value(1, $id);
                $stmt->bind_value(2, $data, Parameter_Type::LARGE_OBJECT);
            };
            $stmt->bind_value(3, $lifetime, Parameter_Type::INTEGER);
            $stmt->bind_value(4, $now, Parameter_Type::INTEGER);
        } else {
            $stmt->bind_value(2, $lifetime, Parameter_Type::INTEGER);
            $stmt->bind_value(3, $now, Parameter_Type::INTEGER);
            $insert_stmt = $this->conn->prepare($insert_sql);
            $insert_stmt->bind_value(3, $lifetime, Parameter_Type::INTEGER);
            $insert_stmt->bind_value(4, $now, Parameter_Type::INTEGER);
            $bind = static function ($id, $data) use ($stmt, $insert_stmt): void {
                $stmt->bind_value(1, $data, Parameter_Type::LARGE_OBJECT);
                $stmt->bind_value(4, $id);
                $insert_stmt->bind_value(1, $id);
                $insert_stmt->bind_value(2, $data, Parameter_Type::LARGE_OBJECT);
            };
        }
        foreach ($values as $id => $data) {
            $bind($id, $data);
            try {
                $row_count = $stmt->execute_statement();
            } catch (Table_Not_Found_Exception) {
                if (!$this->conn->is_transaction_active() || \in_array($platform_name, ['pgsql', 'sqlite', 'sqlsrv'], true)) {
                    $this->create_table();
                }
                $row_count = $stmt->execute_statement();
            }
            if (null === $platform_name && 0 === $row_count) {
                try {
                    $insert_stmt->execute_statement();
                } catch (Dbal_Exception) {
                    // A concurrent write won, let it be
                }
            }
        }
        return $failed;
    }
    /**
     * @internal
     */
    protected function get_id(mixed $key, ?string $namespace = null): string
    {
        if ('pgsql' !== $this->platform_name ??= $this->get_platform_name()) {
            return parent::get_id($key, $namespace);
        }
        if (str_contains((string) $key, "\x00") || str_contains((string) $key, '%') || !preg_match('//u', (string) $key)) {
            $key = rawurlencode((string) $key);
        }
        return parent::get_id($key, $namespace);
    }
    private function get_platform_name(): string
    {
        if (isset($this->platform_name)) {
            return $this->platform_name;
        }
        $platform = $this->conn->get_database_platform();
        return $this->platform_name = match (true) {
            $platform instanceof Abstract_My_Sql_Platform => 'mysql',
            $platform instanceof Sq_Lite_Platform => 'sqlite',
            $platform instanceof Postgre_Sql_Platform => 'pgsql',
            $platform instanceof Oracle_Platform => 'oci',
            $platform instanceof Sql_Server_Platform => 'sqlsrv',
            default => $platform::class,
        };
    }
    private function add_table_to_schema(Schema $schema): void
    {
        $types = ['mysql' => 'binary', 'sqlite' => 'text'];
        $table = $schema->create_table($this->table);
        $table->add_column($this->id_col, $types[$this->get_platform_name()] ?? 'string', ['length' => 255]);
        $table->add_column($this->data_col, 'blob', ['length' => 16777215]);
        $table->add_column($this->lifetime_col, 'integer', ['unsigned' => true, 'notnull' => false]);
        $table->add_column($this->time_col, 'integer', ['unsigned' => true]);
        $table->add_primary_key_constraint(new Primary_Key_Constraint(null, [new Unqualified_Name(Identifier::unquoted($this->id_col))], true));
    }
}
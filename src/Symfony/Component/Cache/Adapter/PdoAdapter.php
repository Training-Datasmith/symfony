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

use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\Default_Marshaller;
use Symfony\Component\Cache\Marshaller\Marshaller_Interface;
use Symfony\Component\Cache\Pruneable_Interface;
class Pdo_Adapter extends Abstract_Adapter implements Pruneable_Interface
{
    private const MAX_KEY_LENGTH = 255;
    private \PDO $conn;
    private string $dsn;
    private string $driver;
    private string $server_version;
    private string $table = 'cache_items';
    private string $id_col = 'item_id';
    private string $data_col = 'item_data';
    private string $lifetime_col = 'item_lifetime';
    private string $time_col = 'item_time';
    private ?string $username = null;
    private ?string $password = null;
    private array $connection_options = [];
    private readonly string $namespace;
    /**
     * You can either pass an existing database connection as PDO instance or
     * a DSN string that will be used to lazy-connect to the database when the
     * cache is actually used.
     *
     * List of available options:
     *  * db_table: The name of the table [default: cache_items]
     *  * db_id_col: The column where to store the cache id [default: item_id]
     *  * db_data_col: The column where to store the cache data [default: item_data]
     *  * db_lifetime_col: The column where to store the lifetime [default: item_lifetime]
     *  * db_time_col: The column where to store the timestamp [default: item_time]
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: []]
     *
     * @throws InvalidArgumentException When first argument is not PDO nor Connection nor string
     * @throws InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     * @throws InvalidArgumentException When namespace contains invalid characters
     */
    public function __construct(
        #[\Sensitive_Parameter]
        \PDO|string $conn_or_dsn,
        string $namespace = '',
        int $default_lifetime = 0,
        array $options = [],
        private readonly ?Marshaller_Interface $marshaller = new Default_Marshaller()
    )
    {
        if (\is_string($conn_or_dsn) && str_contains($conn_or_dsn, '://')) {
            throw new InvalidArgumentException(\sprintf('Usage of Doctrine DBAL URL with "%s" is not supported. Use a PDO DSN or "%s" instead.', self::class, Doctrine_Dbal_Adapter::class));
        }
        if (isset($namespace[0]) && preg_match('#[^-+.A-Za-z0-9]#', $namespace, $match)) {
            throw new InvalidArgumentException(\sprintf('Namespace contains "%s" but only characters in [-+.A-Za-z0-9] are allowed.', $match[0]));
        }
        if ($conn_or_dsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $conn_or_dsn->get_attribute(\PDO::ATTR_ERRMODE)) {
                throw new InvalidArgumentException(\sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', self::class));
            }
            $this->conn = $conn_or_dsn;
        } else {
            $this->dsn = $conn_or_dsn;
        }
        $this->max_id_length = self::MAX_KEY_LENGTH;
        $this->table = $options['db_table'] ?? $this->table;
        $this->id_col = $options['db_id_col'] ?? $this->id_col;
        $this->data_col = $options['db_data_col'] ?? $this->data_col;
        $this->lifetime_col = $options['db_lifetime_col'] ?? $this->lifetime_col;
        $this->time_col = $options['db_time_col'] ?? $this->time_col;
        $this->username = $options['db_username'] ?? $this->username;
        $this->password = $options['db_password'] ?? $this->password;
        $this->connection_options = $options['db_connection_options'] ?? $this->connection_options;
        $this->namespace = $namespace;
        parent::__construct($namespace, $default_lifetime);
    }
    public static function create_connection(
        #[\Sensitive_Parameter]
        string $dsn,
        array $options = []
    ): \PDO|string
    {
        if ($options['lazy'] ?? true) {
            return $dsn;
        }
        $pdo = new \PDO($dsn);
        $pdo->set_attribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
    /**
     * Creates the table to store cache items which can be called once for setup.
     *
     * Cache ID are saved in a column of maximum length 255. Cache data is
     * saved in a BLOB.
     *
     * @throws \PDOException    When the table already exists
     * @throws \DomainException When an unsupported PDO driver is used
     */
    public function create_table(): void
    {
        $sql = match ($driver = $this->get_driver()) {
            // We use varbinary for the ID column because it prevents unwanted conversions:
            // - character set conversions between server and client
            // - trailing space removal
            // - case-insensitivity
            // - language processing like é == e
            'mysql' => "CREATE TABLE {$this->table} ({$this->id_col} VARBINARY(255) NOT NULL PRIMARY KEY, {$this->data_col} MEDIUMBLOB NOT NULL, {$this->lifetime_col} INTEGER UNSIGNED, {$this->time_col} INTEGER UNSIGNED NOT NULL) ENGINE = InnoDB",
            'sqlite' => "CREATE TABLE {$this->table} ({$this->id_col} TEXT NOT NULL PRIMARY KEY, {$this->data_col} BLOB NOT NULL, {$this->lifetime_col} INTEGER, {$this->time_col} INTEGER NOT NULL)",
            'pgsql' => "CREATE TABLE {$this->table} ({$this->id_col} VARCHAR(255) NOT NULL PRIMARY KEY, {$this->data_col} BYTEA NOT NULL, {$this->lifetime_col} INTEGER, {$this->time_col} INTEGER NOT NULL)",
            'oci' => "CREATE TABLE {$this->table} ({$this->id_col} VARCHAR2(255) NOT NULL PRIMARY KEY, {$this->data_col} BLOB NOT NULL, {$this->lifetime_col} INTEGER, {$this->time_col} INTEGER NOT NULL)",
            'sqlsrv' => "CREATE TABLE {$this->table} ({$this->id_col} VARCHAR(255) NOT NULL PRIMARY KEY, {$this->data_col} VARBINARY(MAX) NOT NULL, {$this->lifetime_col} INTEGER, {$this->time_col} INTEGER NOT NULL)",
            default => throw new \DomainException(\sprintf('Creating the cache table is currently not implemented for PDO driver "%s".', $driver)),
        };
        $this->get_connection()->exec($sql);
    }
    public function prune(): bool
    {
        $delete_sql = "DELETE FROM {$this->table} WHERE {$this->lifetime_col} + {$this->time_col} <= :time";
        if ('' !== $this->namespace) {
            $delete_sql .= " AND {$this->id_col} LIKE :namespace";
        }
        $connection = $this->get_connection();
        try {
            $delete = $connection->prepare($delete_sql);
        } catch (\PDOException) {
            return true;
        }
        $delete->bind_value(':time', time(), \PDO::PARAM_INT);
        if ('' !== $this->namespace) {
            $delete->bind_value(':namespace', \sprintf('%s%%', $this->namespace), \PDO::PARAM_STR);
        }
        try {
            return $delete->execute();
        } catch (\PDOException) {
            return true;
        }
    }
    protected function do_fetch(array $ids): iterable
    {
        $connection = $this->get_connection();
        $now = time();
        $expired = [];
        $sql = str_pad('', (\count($ids) << 1) - 1, '?,');
        $sql = "SELECT {$this->id_col}, CASE WHEN {$this->lifetime_col} IS NULL OR {$this->lifetime_col} + {$this->time_col} > ? THEN {$this->data_col} ELSE NULL END FROM {$this->table} WHERE {$this->id_col} IN ({$sql})";
        $stmt = $connection->prepare($sql);
        $stmt->bind_value($i = 1, $now, \PDO::PARAM_INT);
        foreach ($ids as $id) {
            $stmt->bind_value(++$i, $id);
        }
        $result = $stmt->execute();
        if (\is_object($result)) {
            $result = $result->iterate_numeric();
        } else {
            $stmt->set_fetch_mode(\PDO::FETCH_NUM);
            $result = $stmt;
        }
        foreach ($result as $row) {
            if (null === $row[1]) {
                $expired[] = $row[0];
            } else {
                yield $row[0] => $this->marshaller->unmarshall(\is_resource($row[1]) ? stream_get_contents($row[1]) : $row[1]);
            }
        }
        if ($expired) {
            $sql = str_pad('', (\count($expired) << 1) - 1, '?,');
            $sql = "DELETE FROM {$this->table} WHERE {$this->lifetime_col} + {$this->time_col} <= ? AND {$this->id_col} IN ({$sql})";
            $stmt = $connection->prepare($sql);
            $stmt->bind_value($i = 1, $now, \PDO::PARAM_INT);
            foreach ($expired as $id) {
                $stmt->bind_value(++$i, $id);
            }
            $stmt->execute();
        }
    }
    protected function do_have(string $id): bool
    {
        $connection = $this->get_connection();
        $sql = "SELECT 1 FROM {$this->table} WHERE {$this->id_col} = :id AND ({$this->lifetime_col} IS NULL OR {$this->lifetime_col} + {$this->time_col} > :time)";
        $stmt = $connection->prepare($sql);
        $stmt->bind_value(':id', $id);
        $stmt->bind_value(':time', time(), \PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetch_column();
    }
    protected function do_clear(string $namespace): bool
    {
        $conn = $this->get_connection();
        if ('' === $namespace) {
            if ('sqlite' === $this->get_driver()) {
                $sql = "DELETE FROM {$this->table}";
            } else {
                $sql = "TRUNCATE TABLE {$this->table}";
            }
        } else {
            $sql = "DELETE FROM {$this->table} WHERE {$this->id_col} LIKE '{$namespace}%'";
        }
        try {
            $conn->exec($sql);
        } catch (\PDOException) {
        }
        return true;
    }
    protected function do_delete(array $ids): bool
    {
        $sql = str_pad('', (\count($ids) << 1) - 1, '?,');
        $sql = "DELETE FROM {$this->table} WHERE {$this->id_col} IN ({$sql})";
        try {
            $stmt = $this->get_connection()->prepare($sql);
            $stmt->execute(array_values($ids));
        } catch (\PDOException) {
        }
        return true;
    }
    protected function do_save(array $values, int $lifetime): array|bool
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }
        $conn = $this->get_connection();
        $driver = $this->get_driver();
        $insert_sql = "INSERT INTO {$this->table} ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (:id, :data, :lifetime, :time)";
        switch (true) {
            case 'mysql' === $driver:
                $sql = $insert_sql . " ON DUPLICATE KEY UPDATE {$this->data_col} = VALUES({$this->data_col}), {$this->lifetime_col} = VALUES({$this->lifetime_col}), {$this->time_col} = VALUES({$this->time_col})";
                break;
            case 'oci' === $driver:
                // DUAL is Oracle specific dummy table
                $sql = "MERGE INTO {$this->table} USING DUAL ON ({$this->id_col} = ?) " . "WHEN NOT MATCHED THEN INSERT ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (?, ?, ?, ?) " . "WHEN MATCHED THEN UPDATE SET {$this->data_col} = ?, {$this->lifetime_col} = ?, {$this->time_col} = ?";
                break;
            case 'sqlsrv' === $driver && version_compare($this->get_server_version(), '10', '>='):
                // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                // It also requires HOLDLOCK according to http://weblogs.sqlteam.com/dang/archive/2009/01/31/UPSERT-Race-Condition-With-MERGE.aspx
                $sql = "MERGE INTO {$this->table} WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON ({$this->id_col} = ?) " . "WHEN NOT MATCHED THEN INSERT ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (?, ?, ?, ?) " . "WHEN MATCHED THEN UPDATE SET {$this->data_col} = ?, {$this->lifetime_col} = ?, {$this->time_col} = ?;";
                break;
            case 'sqlite' === $driver:
                $sql = 'INSERT OR REPLACE' . substr($insert_sql, 6);
                break;
            case 'pgsql' === $driver && version_compare($this->get_server_version(), '9.5', '>='):
                $sql = $insert_sql . " ON CONFLICT ({$this->id_col}) DO UPDATE SET ({$this->data_col}, {$this->lifetime_col}, {$this->time_col}) = (EXCLUDED.{$this->data_col}, EXCLUDED.{$this->lifetime_col}, EXCLUDED.{$this->time_col})";
                break;
            default:
                $driver = null;
                $sql = "UPDATE {$this->table} SET {$this->data_col} = :data, {$this->lifetime_col} = :lifetime, {$this->time_col} = :time WHERE {$this->id_col} = :id";
                break;
        }
        $now = time();
        $lifetime = $lifetime ?: null;
        try {
            $stmt = $conn->prepare($sql);
        } catch (\PDOException $e) {
            if ($this->is_table_missing($e) && (!$conn->in_transaction() || \in_array($driver, ['pgsql', 'sqlite', 'sqlsrv'], true))) {
                $this->create_table();
            }
            $stmt = $conn->prepare($sql);
        }
        // $id and $data are defined later in the loop. Binding is done by reference, values are read on execution.
        if ('sqlsrv' === $driver || 'oci' === $driver) {
            $stmt->bind_param(1, $id);
            $stmt->bind_param(2, $id);
            $stmt->bind_param(3, $data, \PDO::PARAM_LOB);
            $stmt->bind_value(4, $lifetime, \PDO::PARAM_INT);
            $stmt->bind_value(5, $now, \PDO::PARAM_INT);
            $stmt->bind_param(6, $data, \PDO::PARAM_LOB);
            $stmt->bind_value(7, $lifetime, \PDO::PARAM_INT);
            $stmt->bind_value(8, $now, \PDO::PARAM_INT);
        } else {
            $stmt->bind_param(':id', $id);
            $stmt->bind_param(':data', $data, \PDO::PARAM_LOB);
            $stmt->bind_value(':lifetime', $lifetime, \PDO::PARAM_INT);
            $stmt->bind_value(':time', $now, \PDO::PARAM_INT);
        }
        if (null === $driver) {
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param(':id', $id);
            $insert_stmt->bind_param(':data', $data, \PDO::PARAM_LOB);
            $insert_stmt->bind_value(':lifetime', $lifetime, \PDO::PARAM_INT);
            $insert_stmt->bind_value(':time', $now, \PDO::PARAM_INT);
        }
        if ('sqlsrv' === $driver) {
            $data_stream = fopen('php://memory', 'r+');
        }
        foreach ($values as $data) {
            if ('sqlsrv' === $driver) {
                rewind($data_stream);
                fwrite($data_stream, (string) $data);
                ftruncate($data_stream, \strlen((string) $data));
                rewind($data_stream);
                $data = $data_stream;
            }
            try {
                $stmt->execute();
            } catch (\PDOException $e) {
                if ($this->is_table_missing($e) && (!$conn->in_transaction() || \in_array($driver, ['pgsql', 'sqlite', 'sqlsrv'], true))) {
                    $this->create_table();
                }
                $stmt->execute();
            }
            if (null === $driver && !$stmt->row_count()) {
                try {
                    $insert_stmt->execute();
                } catch (\PDOException) {
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
        if ('pgsql' !== $this->get_driver()) {
            return parent::get_id($key, $namespace);
        }
        if (str_contains((string) $key, "\x00") || str_contains((string) $key, '%') || !preg_match('//u', (string) $key)) {
            $key = rawurlencode((string) $key);
        }
        return parent::get_id($key, $namespace);
    }
    private function get_connection(): \PDO
    {
        if (!isset($this->conn)) {
            $this->conn = new \PDO($this->dsn, $this->username, $this->password, $this->connection_options);
            $this->conn->set_attribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        return $this->conn;
    }
    private function get_driver(): string
    {
        return $this->driver ??= $this->get_connection()->get_attribute(\PDO::ATTR_DRIVER_NAME);
    }
    private function get_server_version(): string
    {
        return $this->server_version ??= $this->get_connection()->get_attribute(\PDO::ATTR_SERVER_VERSION);
    }
    private function is_table_missing(\PDOException $exception): bool
    {
        $driver = $this->get_driver();
        [$sql_state, $code] = $exception->error_info ?? [null, $exception->get_code()];
        return match ($driver) {
            'pgsql' => '42P01' === $sql_state,
            'sqlite' => str_contains($exception->get_message(), 'no such table:'),
            'oci' => 942 === $code,
            'sqlsrv' => 208 === $code,
            'mysql' => 1146 === $code,
            default => false,
        };
    }
}
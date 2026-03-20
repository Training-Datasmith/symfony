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
namespace Symfony\Component\Http_Foundation\Session\Storage\Handler;

use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\Unqualified_Name;
use Doctrine\DBAL\Schema\Primary_Key_Constraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
/**
 * Session handler using a PDO connection to read and write data.
 *
 * It works with MySQL, PostgreSQL, Oracle, SQL Server and SQLite and implements
 * different locking strategies to handle concurrent access to the same session.
 * Locking is necessary to prevent loss of data due to race conditions and to keep
 * the session data consistent between read() and write(). With locking, requests
 * for the same session will wait until the other one finished writing. For this
 * reason it's best practice to close a session as early as possible to improve
 * concurrency. PHPs internal files session handler also implements locking.
 *
 * Attention: Since SQLite does not support row level locks but locks the whole database,
 * it means only one session can be accessed at a time. Even different sessions would wait
 * for another to finish. So saving session in SQLite should only be considered for
 * development or prototypes.
 *
 * Session data is a binary string that can contain non-printable characters like the null byte.
 * For this reason it must be saved in a binary column in the database like BLOB in MySQL.
 * Saving it in a character column could corrupt the data. You can use createTable()
 * to initialize a correctly defined table.
 *
 * @see https://php.net/sessionhandlerinterface
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Michael Williams <michael.williams@funsational.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class Pdo_Session_Handler extends Abstract_Session_Handler
{
    /**
     * No locking is done. This means sessions are prone to loss of data due to
     * race conditions of concurrent requests to the same session. The last session
     * write will win in this case. It might be useful when you implement your own
     * logic to deal with this like an optimistic approach.
     */
    public const LOCK_NONE = 0;
    /**
     * Creates an application-level lock on a session. The disadvantage is that the
     * lock is not enforced by the database and thus other, unaware parts of the
     * application could still concurrently modify the session. The advantage is it
     * does not require a transaction.
     * This mode is not available for SQLite and not yet implemented for oci and sqlsrv.
     */
    public const LOCK_ADVISORY = 1;
    /**
     * Issues a real row lock. Since it uses a transaction between opening and
     * closing a session, you have to be careful when you use same database connection
     * that you also use for your application logic. This mode is the default because
     * it's the only reliable solution across DBMSs.
     */
    public const LOCK_TRANSACTIONAL = 2;
    private \PDO $pdo;
    /**
     * DSN string or null for session.save_path or false when lazy connection disabled.
     */
    private string|false|null $dsn = false;
    private string $driver;
    private string $table = 'sessions';
    private string $id_col = 'sess_id';
    private string $data_col = 'sess_data';
    private string $lifetime_col = 'sess_lifetime';
    private string $time_col = 'sess_time';
    /**
     * Time to live in seconds.
     */
    private readonly int|\Closure|null $ttl;
    /**
     * Username when lazy-connect.
     */
    private ?string $username = null;
    /**
     * Password when lazy-connect.
     */
    private ?string $password = null;
    /**
     * Connection options when lazy-connect.
     */
    private array $connection_options = [];
    /**
     * The strategy for locking, see constants.
     */
    private int $lock_mode = self::LOCK_TRANSACTIONAL;
    /**
     * It's an array to support multiple reads before closing which is manual, non-standard usage.
     *
     * @var \PDOStatement[] An array of statements to release advisory locks
     */
    private array $unlock_statements = [];
    /**
     * True when the current session exists but expired according to session.gc_maxlifetime.
     */
    private bool $session_expired = false;
    /**
     * Whether a transaction is active.
     */
    private bool $in_transaction = false;
    /**
     * Whether gc() has been called.
     */
    private bool $gc_called = false;
    /**
     * You can either pass an existing database connection as PDO instance or
     * pass a DSN string that will be used to lazy-connect to the database
     * when the session is actually used. Furthermore it's possible to pass null
     * which will then use the session.save_path ini setting as PDO DSN parameter.
     *
     * List of available options:
     *  * db_table: The name of the table [default: sessions]
     *  * db_id_col: The column where to store the session id [default: sess_id]
     *  * db_data_col: The column where to store the session data [default: sess_data]
     *  * db_lifetime_col: The column where to store the lifetime [default: sess_lifetime]
     *  * db_time_col: The column where to store the timestamp [default: sess_time]
     *  * db_username: The username when lazy-connect [default: '']
     *  * db_password: The password when lazy-connect [default: '']
     *  * db_connection_options: An array of driver-specific connection options [default: []]
     *  * lock_mode: The strategy for locking, see constants [default: LOCK_TRANSACTIONAL]
     *  * ttl: The time to live in seconds.
     *
     * @param \PDO|string|null $pdoOrDsn A \PDO instance or DSN string or URL string or null
     *
     * @throws \InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
     */
    public function __construct(
        #[\Sensitive_Parameter]
        \PDO|string|null $pdo_or_dsn = null,
        #[\Sensitive_Parameter]
        array $options = []
    )
    {
        if ($pdo_or_dsn instanceof \PDO) {
            if (\PDO::ERRMODE_EXCEPTION !== $pdo_or_dsn->get_attribute(\PDO::ATTR_ERRMODE)) {
                throw new \InvalidArgumentException(\sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)).', self::class));
            }
            $this->pdo = $pdo_or_dsn;
            $this->driver = $this->pdo->get_attribute(\PDO::ATTR_DRIVER_NAME);
        } elseif (\is_string($pdo_or_dsn) && str_contains($pdo_or_dsn, '://')) {
            $this->dsn = $this->build_dsn_from_url($pdo_or_dsn);
        } else {
            $this->dsn = $pdo_or_dsn;
        }
        $this->table = $options['db_table'] ?? $this->table;
        $this->id_col = $options['db_id_col'] ?? $this->id_col;
        $this->data_col = $options['db_data_col'] ?? $this->data_col;
        $this->lifetime_col = $options['db_lifetime_col'] ?? $this->lifetime_col;
        $this->time_col = $options['db_time_col'] ?? $this->time_col;
        $this->username = $options['db_username'] ?? $this->username;
        $this->password = $options['db_password'] ?? $this->password;
        $this->connection_options = $options['db_connection_options'] ?? $this->connection_options;
        $this->lock_mode = $options['lock_mode'] ?? $this->lock_mode;
        $this->ttl = $options['ttl'] ?? null;
    }
    /**
     * Adds the Table to the Schema if it doesn't exist.
     */
    public function configure_schema(Schema $schema, ?\Closure $is_same_database = null): void
    {
        if ($schema->has_table($this->table) || $is_same_database && !$is_same_database($this->get_connection()->exec(...))) {
            return;
        }
        $table = $schema->create_table($this->table);
        switch ($this->driver) {
            case 'mysql':
                $table->add_column($this->id_col, Types::BINARY)->set_length(128)->set_notnull(true);
                $table->add_column($this->data_col, Types::BLOB)->set_notnull(true);
                $table->add_column($this->lifetime_col, Types::INTEGER)->set_unsigned(true)->set_notnull(true);
                $table->add_column($this->time_col, Types::INTEGER)->set_unsigned(true)->set_notnull(true);
                $table->add_option('engine', 'InnoDB');
                break;
            case 'sqlite':
                $table->add_column($this->id_col, Types::TEXT)->set_notnull(true);
                $table->add_column($this->data_col, Types::BLOB)->set_notnull(true);
                $table->add_column($this->lifetime_col, Types::INTEGER)->set_notnull(true);
                $table->add_column($this->time_col, Types::INTEGER)->set_notnull(true);
                break;
            case 'pgsql':
                $table->add_column($this->id_col, Types::STRING)->set_length(128)->set_notnull(true);
                $table->add_column($this->data_col, Types::BINARY)->set_notnull(true);
                $table->add_column($this->lifetime_col, Types::INTEGER)->set_notnull(true);
                $table->add_column($this->time_col, Types::INTEGER)->set_notnull(true);
                break;
            case 'oci':
                $table->add_column($this->id_col, Types::STRING)->set_length(128)->set_notnull(true);
                $table->add_column($this->data_col, Types::BLOB)->set_notnull(true);
                $table->add_column($this->lifetime_col, Types::INTEGER)->set_notnull(true);
                $table->add_column($this->time_col, Types::INTEGER)->set_notnull(true);
                break;
            case 'sqlsrv':
                $table->add_column($this->id_col, Types::STRING)->set_length(128)->set_notnull(true);
                $table->add_column($this->data_col, Types::BLOB)->set_notnull(true);
                $table->add_column($this->lifetime_col, Types::INTEGER)->set_unsigned(true)->set_notnull(true);
                $table->add_column($this->time_col, Types::INTEGER)->set_unsigned(true)->set_notnull(true);
                break;
            default:
                throw new \DomainException(\sprintf('Creating the session table is currently not implemented for PDO driver "%s".', $this->driver));
        }
        $table->add_primary_key_constraint(new Primary_Key_Constraint(null, [new Unqualified_Name(Identifier::unquoted($this->id_col))], true));
        $table->add_index([$this->lifetime_col], $this->lifetime_col . '_idx');
    }
    /**
     * Creates the table to store sessions which can be called once for setup.
     *
     * Session ID is saved in a column of maximum length 128 because that is enough even
     * for a 512 bit configured session.hash_function like Whirlpool. Session data is
     * saved in a BLOB. One could also use a shorter inlined varbinary column
     * if one was sure the data fits into it.
     *
     * @throws \PDOException    When the table already exists
     * @throws \DomainException When an unsupported PDO driver is used
     */
    public function create_table(): void
    {
        // connect if we are not yet
        $this->get_connection();
        $sql = match ($this->driver) {
            // We use varbinary for the ID column because it prevents unwanted conversions:
            // - character set conversions between server and client
            // - trailing space removal
            // - case-insensitivity
            // - language processing like é == e
            'mysql' => "CREATE TABLE {$this->table} ({$this->id_col} VARBINARY(128) NOT NULL PRIMARY KEY, {$this->data_col} BLOB NOT NULL, {$this->lifetime_col} INTEGER UNSIGNED NOT NULL, {$this->time_col} INTEGER UNSIGNED NOT NULL) ENGINE = InnoDB",
            'sqlite' => "CREATE TABLE {$this->table} ({$this->id_col} TEXT NOT NULL PRIMARY KEY, {$this->data_col} BLOB NOT NULL, {$this->lifetime_col} INTEGER NOT NULL, {$this->time_col} INTEGER NOT NULL)",
            'pgsql' => "CREATE TABLE {$this->table} ({$this->id_col} VARCHAR(128) NOT NULL PRIMARY KEY, {$this->data_col} BYTEA NOT NULL, {$this->lifetime_col} INTEGER NOT NULL, {$this->time_col} INTEGER NOT NULL)",
            'oci' => "CREATE TABLE {$this->table} ({$this->id_col} VARCHAR2(128) NOT NULL PRIMARY KEY, {$this->data_col} BLOB NOT NULL, {$this->lifetime_col} INTEGER NOT NULL, {$this->time_col} INTEGER NOT NULL)",
            'sqlsrv' => "CREATE TABLE {$this->table} ({$this->id_col} VARCHAR(128) NOT NULL PRIMARY KEY, {$this->data_col} VARBINARY(MAX) NOT NULL, {$this->lifetime_col} INTEGER NOT NULL, {$this->time_col} INTEGER NOT NULL)",
            default => throw new \DomainException(\sprintf('Creating the session table is currently not implemented for PDO driver "%s".', $this->driver)),
        };
        try {
            $this->pdo->exec($sql);
            $this->pdo->exec("CREATE INDEX {$this->lifetime_col}_idx ON {$this->table} ({$this->lifetime_col})");
        } catch (\PDOException $e) {
            $this->rollback();
            throw $e;
        }
    }
    /**
     * Returns true when the current session exists but expired according to session.gc_maxlifetime.
     *
     * Can be used to distinguish between a new session and one that expired due to inactivity.
     */
    public function is_session_expired(): bool
    {
        return $this->session_expired;
    }
    public function open(string $save_path, string $session_name): bool
    {
        $this->session_expired = false;
        if (!isset($this->pdo)) {
            $this->connect($this->dsn ?: $save_path);
        }
        return parent::open($save_path, $session_name);
    }
    public function read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string
    {
        try {
            return parent::read($session_id);
        } catch (\PDOException $e) {
            $this->rollback();
            throw $e;
        }
    }
    public function gc(int $maxlifetime): int|false
    {
        // We delay gc() to close() so that it is executed outside the transactional and blocking read-write process.
        // This way, pruning expired sessions does not block them from being started while the current session is used.
        $this->gc_called = true;
        return 0;
    }
    protected function do_destroy(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        // delete the record associated with this id
        $sql = "DELETE FROM {$this->table} WHERE {$this->id_col} = :id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bind_param(':id', $session_id, \PDO::PARAM_STR);
            $stmt->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            throw $e;
        }
        return true;
    }
    protected function do_write(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        $maxlifetime = (int) (($this->ttl instanceof \Closure ? ($this->ttl)() : $this->ttl) ?? \ini_get('session.gc_maxlifetime'));
        try {
            // We use a single MERGE SQL query when supported by the database.
            $merge_stmt = $this->get_merge_statement($session_id, $data, $maxlifetime);
            if (null !== $merge_stmt) {
                $merge_stmt->execute();
                return true;
            }
            $update_stmt = $this->get_update_statement($session_id, $data, $maxlifetime);
            $update_stmt->execute();
            // When MERGE is not supported, like in Postgres < 9.5, we have to use this approach that can result in
            // duplicate key errors when the same session is written simultaneously (given the LOCK_NONE behavior).
            // We can just catch such an error and re-execute the update. This is similar to a serializable
            // transaction with retry logic on serialization failures but without the overhead and without possible
            // false positives due to longer gap locking.
            if (!$update_stmt->row_count()) {
                try {
                    $insert_stmt = $this->get_insert_statement($session_id, $data, $maxlifetime);
                    $insert_stmt->execute();
                } catch (\PDOException $e) {
                    // Handle integrity violation SQLSTATE 23000 (or a subclass like 23505 in Postgres) for duplicate keys
                    if (str_starts_with((string) $e->get_code(), '23')) {
                        $update_stmt->execute();
                    } else {
                        throw $e;
                    }
                }
            }
        } catch (\PDOException $e) {
            $this->rollback();
            throw $e;
        }
        return true;
    }
    public function update_timestamp(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        $expiry = time() + (int) (($this->ttl instanceof \Closure ? ($this->ttl)() : $this->ttl) ?? \ini_get('session.gc_maxlifetime'));
        try {
            $update_stmt = $this->pdo->prepare("UPDATE {$this->table} SET {$this->lifetime_col} = :expiry, {$this->time_col} = :time WHERE {$this->id_col} = :id");
            $update_stmt->bind_value(':id', $session_id, \PDO::PARAM_STR);
            $update_stmt->bind_value(':expiry', $expiry, \PDO::PARAM_INT);
            $update_stmt->bind_value(':time', time(), \PDO::PARAM_INT);
            $update_stmt->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            throw $e;
        }
        return true;
    }
    public function close(): bool
    {
        $this->commit();
        while ($unlock_stmt = array_shift($this->unlock_statements)) {
            $unlock_stmt->execute();
        }
        if ($this->gc_called) {
            $this->gc_called = false;
            // delete the session records that have expired
            $sql = "DELETE FROM {$this->table} WHERE {$this->lifetime_col} < :time";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bind_value(':time', time(), \PDO::PARAM_INT);
            $stmt->execute();
        }
        if (false !== $this->dsn) {
            unset($this->pdo, $this->driver);
            // only close lazy-connection
        }
        return true;
    }
    /**
     * Lazy-connects to the database.
     */
    private function connect(
        #[\Sensitive_Parameter]
        string $dsn
    ): void
    {
        $this->pdo = new \PDO($dsn, $this->username, $this->password, $this->connection_options);
        $this->pdo->set_attribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->driver = $this->pdo->get_attribute(\PDO::ATTR_DRIVER_NAME);
    }
    /**
     * Builds a PDO DSN from a URL-like connection string.
     *
     * @todo implement missing support for oci DSN (which look totally different from other PDO ones)
     */
    private function build_dsn_from_url(
        #[\Sensitive_Parameter]
        string $dsn_or_url
    ): string
    {
        // (pdo_)?sqlite3?:///... => (pdo_)?sqlite3?://localhost/... or else the URL will be invalid
        $url = preg_replace('#^((?:pdo_)?sqlite3?):///#', '$1://localhost/', $dsn_or_url);
        $params = parse_url((string) $url);
        if (false === $params) {
            return $dsn_or_url;
            // If the URL is not valid, let's assume it might be a DSN already.
        }
        $params = array_map(rawurldecode(...), $params);
        // Override the default username and password. Values passed through options will still win over these in the constructor.
        if (isset($params['user'])) {
            $this->username = $params['user'];
        }
        if (isset($params['pass'])) {
            $this->password = $params['pass'];
        }
        if (!isset($params['scheme'])) {
            throw new \InvalidArgumentException('URLs without scheme are not supported to configure the PdoSessionHandler.');
        }
        $driver_alias_map = [
            'mssql' => 'sqlsrv',
            'mysql2' => 'mysql',
            // Amazon RDS, for some weird reason
            'postgres' => 'pgsql',
            'postgresql' => 'pgsql',
            'sqlite3' => 'sqlite',
        ];
        $driver = $driver_alias_map[$params['scheme']] ?? $params['scheme'];
        // Doctrine DBAL supports passing its internal pdo_* driver names directly too (allowing both dashes and underscores). This allows supporting the same here.
        if (str_starts_with($driver, 'pdo_') || str_starts_with($driver, 'pdo-')) {
            $driver = substr($driver, 4);
        }
        $dsn = null;
        switch ($driver) {
            case 'mysql':
                $dsn = 'mysql:';
                if ('' !== ($params['query'] ?? '')) {
                    $query_params = [];
                    parse_str($params['query'], $query_params);
                    if ('' !== ($query_params['charset'] ?? '')) {
                        $dsn .= 'charset=' . $query_params['charset'] . ';';
                    }
                    if ('' !== ($query_params['unix_socket'] ?? '')) {
                        $dsn .= 'unix_socket=' . $query_params['unix_socket'] . ';';
                        if (isset($params['path'])) {
                            $db_name = substr($params['path'], 1);
                            // Remove the leading slash
                            $dsn .= 'dbname=' . $db_name . ';';
                        }
                        return $dsn;
                    }
                }
            // If "unix_socket" is not in the query, we continue with the same process as pgsql
            // no break
            case 'pgsql':
                $dsn ??= 'pgsql:';
                if (isset($params['host']) && '' !== $params['host']) {
                    $dsn .= 'host=' . $params['host'] . ';';
                }
                if (isset($params['port']) && '' !== $params['port']) {
                    $dsn .= 'port=' . $params['port'] . ';';
                }
                if (isset($params['path'])) {
                    $db_name = substr($params['path'], 1);
                    // Remove the leading slash
                    $dsn .= 'dbname=' . $db_name . ';';
                }
                return $dsn;
            case 'sqlite':
                return 'sqlite:' . substr($params['path'], 1);
            case 'sqlsrv':
                $dsn = 'sqlsrv:server=';
                if (isset($params['host'])) {
                    $dsn .= $params['host'];
                }
                if (isset($params['port']) && '' !== $params['port']) {
                    $dsn .= ',' . $params['port'];
                }
                if (isset($params['path'])) {
                    $db_name = substr($params['path'], 1);
                    // Remove the leading slash
                    $dsn .= ';Database=' . $db_name;
                }
                return $dsn;
            default:
                throw new \InvalidArgumentException(\sprintf('The scheme "%s" is not supported by the PdoSessionHandler URL configuration. Pass a PDO DSN directly.', $params['scheme']));
        }
    }
    /**
     * Helper method to begin a transaction.
     *
     * Since SQLite does not support row level locks, we have to acquire a reserved lock
     * on the database immediately. Because of https://bugs.php.net/42766 we have to create
     * such a transaction manually which also means we cannot use PDO::commit or
     * PDO::rollback or PDO::inTransaction for SQLite.
     *
     * Also MySQLs default isolation, REPEATABLE READ, causes deadlock for different sessions
     * due to https://percona.com/blog/2013/12/12/one-more-innodb-gap-lock-to-avoid/ .
     * So we change it to READ COMMITTED.
     */
    private function begin_transaction(): void
    {
        if (!$this->in_transaction) {
            if ('sqlite' === $this->driver) {
                $this->pdo->exec('BEGIN IMMEDIATE TRANSACTION');
            } else {
                if ('mysql' === $this->driver) {
                    $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                }
                $this->pdo->begin_transaction();
            }
            $this->in_transaction = true;
        }
    }
    /**
     * Helper method to commit a transaction.
     */
    private function commit(): void
    {
        if ($this->in_transaction) {
            try {
                // commit read-write transaction which also releases the lock
                if ('sqlite' === $this->driver) {
                    $this->pdo->exec('COMMIT');
                } else {
                    $this->pdo->commit();
                }
                $this->in_transaction = false;
            } catch (\PDOException $e) {
                $this->rollback();
                throw $e;
            }
        }
    }
    /**
     * Helper method to rollback a transaction.
     */
    private function rollback(): void
    {
        // We only need to rollback if we are in a transaction. Otherwise the resulting
        // error would hide the real problem why rollback was called. We might not be
        // in a transaction when not using the transactional locking behavior or when
        // two callbacks (e.g. destroy and write) are invoked that both fail.
        if ($this->in_transaction) {
            if ('sqlite' === $this->driver) {
                $this->pdo->exec('ROLLBACK');
            } else {
                $this->pdo->roll_back();
            }
            $this->in_transaction = false;
        }
    }
    /**
     * Reads the session data in respect to the different locking strategies.
     *
     * We need to make sure we do not return session data that is already considered garbage according
     * to the session.gc_maxlifetime setting because gc() is called after read() and only sometimes.
     */
    protected function do_read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string
    {
        if (self::LOCK_ADVISORY === $this->lock_mode) {
            $this->unlock_statements[] = $this->do_advisory_lock($session_id);
        }
        $select_sql = $this->get_select_sql();
        $select_stmt = $this->pdo->prepare($select_sql);
        $select_stmt->bind_param(':id', $session_id, \PDO::PARAM_STR);
        $insert_stmt = null;
        while (true) {
            $select_stmt->execute();
            $session_rows = $select_stmt->fetch_all(\PDO::FETCH_NUM);
            if ($session_rows) {
                $expiry = (int) $session_rows[0][1];
                if ($expiry < time()) {
                    $this->session_expired = true;
                    return '';
                }
                return \is_resource($session_rows[0][0]) ? stream_get_contents($session_rows[0][0]) : $session_rows[0][0];
            }
            if (null !== $insert_stmt) {
                $this->rollback();
                throw new \RuntimeException('Failed to read session: INSERT reported a duplicate id but next SELECT did not return any data.');
            }
            if (!filter_var(\ini_get('session.use_strict_mode'), \FILTER_VALIDATE_BOOL) && self::LOCK_TRANSACTIONAL === $this->lock_mode && 'sqlite' !== $this->driver) {
                // In strict mode, session fixation is not possible: new sessions always start with a unique
                // random id, so that concurrency is not possible and this code path can be skipped.
                // Exclusive-reading of non-existent rows does not block, so we need to do an insert to block
                // until other connections to the session are committed.
                try {
                    $insert_stmt = $this->get_insert_statement($session_id, '', 0);
                    $insert_stmt->execute();
                } catch (\PDOException $e) {
                    // Catch duplicate key error because other connection created the session already.
                    // It would only not be the case when the other connection destroyed the session.
                    if (str_starts_with((string) $e->get_code(), '23')) {
                        // Retrieve finished session data written by concurrent connection by restarting the loop.
                        // We have to start a new transaction as a failed query will mark the current transaction as
                        // aborted in PostgreSQL and disallow further queries within it.
                        $this->rollback();
                        $this->begin_transaction();
                        continue;
                    }
                    throw $e;
                }
            }
            return '';
        }
    }
    /**
     * Executes an application-level lock on the database.
     *
     * @return \PDOStatement The statement that needs to be executed later to release the lock
     *
     * @throws \DomainException When an unsupported PDO driver is used
     *
     * @todo implement missing advisory locks
     *       - for oci using DBMS_LOCK.REQUEST
     *       - for sqlsrv using sp_getapplock with LockOwner = Session
     */
    private function do_advisory_lock(
        #[\Sensitive_Parameter]
        string $session_id
    ): \PDOStatement
    {
        switch ($this->driver) {
            case 'mysql':
                // MySQL 5.7.5 and later enforces a maximum length on lock names of 64 characters. Previously, no limit was enforced.
                $lock_id = substr($session_id, 0, 64);
                // should we handle the return value? 0 on timeout, null on error
                // we use a timeout of 50 seconds which is also the default for innodb_lock_wait_timeout
                $stmt = $this->pdo->prepare('SELECT GET_LOCK(:key, 50)');
                $stmt->bind_value(':key', $lock_id, \PDO::PARAM_STR);
                $stmt->execute();
                $release_stmt = $this->pdo->prepare('DO RELEASE_LOCK(:key)');
                $release_stmt->bind_value(':key', $lock_id, \PDO::PARAM_STR);
                return $release_stmt;
            case 'pgsql':
                // Obtaining an exclusive session level advisory lock requires an integer key.
                // When session.sid_bits_per_character > 4, the session id can contain non-hex-characters.
                // So we cannot just use hexdec().
                if (4 === \PHP_INT_SIZE) {
                    $session_int1 = $this->convert_string_to_int($session_id);
                    $session_int2 = $this->convert_string_to_int(substr($session_id, 4, 4));
                    $stmt = $this->pdo->prepare('SELECT pg_advisory_lock(:key1, :key2)');
                    $stmt->bind_value(':key1', $session_int1, \PDO::PARAM_INT);
                    $stmt->bind_value(':key2', $session_int2, \PDO::PARAM_INT);
                    $stmt->execute();
                    $release_stmt = $this->pdo->prepare('SELECT pg_advisory_unlock(:key1, :key2)');
                    $release_stmt->bind_value(':key1', $session_int1, \PDO::PARAM_INT);
                    $release_stmt->bind_value(':key2', $session_int2, \PDO::PARAM_INT);
                } else {
                    $session_big_int = $this->convert_string_to_int($session_id);
                    $stmt = $this->pdo->prepare('SELECT pg_advisory_lock(:key)');
                    $stmt->bind_value(':key', $session_big_int, \PDO::PARAM_INT);
                    $stmt->execute();
                    $release_stmt = $this->pdo->prepare('SELECT pg_advisory_unlock(:key)');
                    $release_stmt->bind_value(':key', $session_big_int, \PDO::PARAM_INT);
                }
                return $release_stmt;
            case 'sqlite':
                throw new \DomainException('SQLite does not support advisory locks.');
            default:
                throw new \DomainException(\sprintf('Advisory locks are currently not implemented for PDO driver "%s".', $this->driver));
        }
    }
    /**
     * Encodes the first 4 (when PHP_INT_SIZE == 4) or 8 characters of the string as an integer.
     *
     * Keep in mind, PHP integers are signed.
     */
    private function convert_string_to_int(string $string): int
    {
        if (4 === \PHP_INT_SIZE) {
            return (\ord($string[3]) << 24) + (\ord($string[2]) << 16) + (\ord($string[1]) << 8) + \ord($string[0]);
        }
        $int1 = (\ord($string[7]) << 24) + (\ord($string[6]) << 16) + (\ord($string[5]) << 8) + \ord($string[4]);
        $int2 = (\ord($string[3]) << 24) + (\ord($string[2]) << 16) + (\ord($string[1]) << 8) + \ord($string[0]);
        return $int2 + ($int1 << 32);
    }
    /**
     * Return a locking or nonlocking SQL query to read session information.
     *
     * @throws \DomainException When an unsupported PDO driver is used
     */
    private function get_select_sql(): string
    {
        if (self::LOCK_TRANSACTIONAL === $this->lock_mode) {
            $this->begin_transaction();
            switch ($this->driver) {
                case 'mysql':
                case 'oci':
                case 'pgsql':
                    return "SELECT {$this->data_col}, {$this->lifetime_col} FROM {$this->table} WHERE {$this->id_col} = :id FOR UPDATE";
                case 'sqlsrv':
                    return "SELECT {$this->data_col}, {$this->lifetime_col} FROM {$this->table} WITH (UPDLOCK, ROWLOCK) WHERE {$this->id_col} = :id";
                case 'sqlite':
                    // we already locked when starting transaction
                    break;
                default:
                    throw new \DomainException(\sprintf('Transactional locks are currently not implemented for PDO driver "%s".', $this->driver));
            }
        }
        return "SELECT {$this->data_col}, {$this->lifetime_col} FROM {$this->table} WHERE {$this->id_col} = :id";
    }
    /**
     * Returns an insert statement supported by the database for writing session data.
     */
    private function get_insert_statement(
        #[\Sensitive_Parameter]
        string $session_id,
        string $session_data,
        int $maxlifetime
    ): \PDOStatement
    {
        switch ($this->driver) {
            case 'oci':
                $data = fopen('php://memory', 'r+');
                fwrite($data, $session_data);
                rewind($data);
                $sql = "INSERT INTO {$this->table} ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (:id, EMPTY_BLOB(), :expiry, :time) RETURNING {$this->data_col} into :data";
                break;
            case 'sqlsrv':
                $data = fopen('php://memory', 'r+');
                fwrite($data, $session_data);
                rewind($data);
                $sql = "INSERT INTO {$this->table} ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (:id, :data, :expiry, :time)";
                break;
            default:
                $data = $session_data;
                $sql = "INSERT INTO {$this->table} ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (:id, :data, :expiry, :time)";
                break;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bind_param(':id', $session_id, \PDO::PARAM_STR);
        $stmt->bind_param(':data', $data, \PDO::PARAM_LOB);
        $stmt->bind_value(':expiry', time() + $maxlifetime, \PDO::PARAM_INT);
        $stmt->bind_value(':time', time(), \PDO::PARAM_INT);
        return $stmt;
    }
    /**
     * Returns an update statement supported by the database for writing session data.
     */
    private function get_update_statement(
        #[\Sensitive_Parameter]
        string $session_id,
        string $session_data,
        int $maxlifetime
    ): \PDOStatement
    {
        switch ($this->driver) {
            case 'oci':
                $data = fopen('php://memory', 'r+');
                fwrite($data, $session_data);
                rewind($data);
                $sql = "UPDATE {$this->table} SET {$this->data_col} = EMPTY_BLOB(), {$this->lifetime_col} = :expiry, {$this->time_col} = :time WHERE {$this->id_col} = :id RETURNING {$this->data_col} into :data";
                break;
            case 'sqlsrv':
                $data = fopen('php://memory', 'r+');
                fwrite($data, $session_data);
                rewind($data);
                $sql = "UPDATE {$this->table} SET {$this->data_col} = :data, {$this->lifetime_col} = :expiry, {$this->time_col} = :time WHERE {$this->id_col} = :id";
                break;
            default:
                $data = $session_data;
                $sql = "UPDATE {$this->table} SET {$this->data_col} = :data, {$this->lifetime_col} = :expiry, {$this->time_col} = :time WHERE {$this->id_col} = :id";
                break;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bind_param(':id', $session_id, \PDO::PARAM_STR);
        $stmt->bind_param(':data', $data, \PDO::PARAM_LOB);
        $stmt->bind_value(':expiry', time() + $maxlifetime, \PDO::PARAM_INT);
        $stmt->bind_value(':time', time(), \PDO::PARAM_INT);
        return $stmt;
    }
    /**
     * Returns a merge/upsert (i.e. insert or update) statement when supported by the database for writing session data.
     */
    private function get_merge_statement(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data,
        int $maxlifetime
    ): ?\PDOStatement
    {
        switch (true) {
            case 'mysql' === $this->driver:
                $merge_sql = "INSERT INTO {$this->table} ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (:id, :data, :expiry, :time) " . "ON DUPLICATE KEY UPDATE {$this->data_col} = VALUES({$this->data_col}), {$this->lifetime_col} = VALUES({$this->lifetime_col}), {$this->time_col} = VALUES({$this->time_col})";
                break;
            case 'sqlsrv' === $this->driver && version_compare($this->pdo->get_attribute(\PDO::ATTR_SERVER_VERSION), '10', '>='):
                // MERGE is only available since SQL Server 2008 and must be terminated by semicolon
                // It also requires HOLDLOCK according to https://weblogs.sqlteam.com/dang/2009/01/31/upsert-race-condition-with-merge/
                $merge_sql = "MERGE INTO {$this->table} WITH (HOLDLOCK) USING (SELECT 1 AS dummy) AS src ON ({$this->id_col} = ?) " . "WHEN NOT MATCHED THEN INSERT ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (?, ?, ?, ?) " . "WHEN MATCHED THEN UPDATE SET {$this->data_col} = ?, {$this->lifetime_col} = ?, {$this->time_col} = ?;";
                break;
            case 'sqlite' === $this->driver:
                $merge_sql = "INSERT OR REPLACE INTO {$this->table} ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (:id, :data, :expiry, :time)";
                break;
            case 'pgsql' === $this->driver && version_compare($this->pdo->get_attribute(\PDO::ATTR_SERVER_VERSION), '9.5', '>='):
                $merge_sql = "INSERT INTO {$this->table} ({$this->id_col}, {$this->data_col}, {$this->lifetime_col}, {$this->time_col}) VALUES (:id, :data, :expiry, :time) " . "ON CONFLICT ({$this->id_col}) DO UPDATE SET ({$this->data_col}, {$this->lifetime_col}, {$this->time_col}) = (EXCLUDED.{$this->data_col}, EXCLUDED.{$this->lifetime_col}, EXCLUDED.{$this->time_col})";
                break;
            default:
                // MERGE is not supported with LOBs: https://oracle.com/technetwork/articles/fuecks-lobs-095315.html
                return null;
        }
        $merge_stmt = $this->pdo->prepare($merge_sql);
        if ('sqlsrv' === $this->driver) {
            $data_stream = fopen('php://memory', 'r+');
            fwrite($data_stream, $data);
            rewind($data_stream);
            $merge_stmt->bind_param(1, $session_id, \PDO::PARAM_STR);
            $merge_stmt->bind_param(2, $session_id, \PDO::PARAM_STR);
            $merge_stmt->bind_param(3, $data_stream, \PDO::PARAM_LOB);
            $merge_stmt->bind_value(4, time() + $maxlifetime, \PDO::PARAM_INT);
            $merge_stmt->bind_value(5, time(), \PDO::PARAM_INT);
            $merge_stmt->bind_param(6, $data_stream, \PDO::PARAM_LOB);
            $merge_stmt->bind_value(7, time() + $maxlifetime, \PDO::PARAM_INT);
            $merge_stmt->bind_value(8, time(), \PDO::PARAM_INT);
        } else {
            $merge_stmt->bind_param(':id', $session_id, \PDO::PARAM_STR);
            $merge_stmt->bind_param(':data', $data, \PDO::PARAM_LOB);
            $merge_stmt->bind_value(':expiry', time() + $maxlifetime, \PDO::PARAM_INT);
            $merge_stmt->bind_value(':time', time(), \PDO::PARAM_INT);
        }
        return $merge_stmt;
    }
    /**
     * Return a PDO instance.
     */
    protected function get_connection(): \PDO
    {
        if (!isset($this->pdo)) {
            $this->connect($this->dsn ?: \ini_get('session.save_path'));
        }
        return $this->pdo;
    }
}
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

use Mongo_Db\BSON\Binary;
use Mongo_Db\BSON\Utc_Date_Time;
use Mongo_Db\Client;
use Mongo_Db\Driver\Bulk_Write;
use Mongo_Db\Driver\Manager;
use Mongo_Db\Driver\Query;
/**
 * Session handler using the MongoDB driver extension.
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * @see https://php.net/mongodb
 */
class Mongo_Db_Session_Handler extends Abstract_Session_Handler
{
    private readonly Manager $manager;
    private readonly string $namespace;
    private array $options;
    private readonly int|\Closure|null $ttl;
    /**
     * Constructor.
     *
     * List of available options:
     *  * database: The name of the database [required]
     *  * collection: The name of the collection [required]
     *  * id_field: The field name for storing the session id [default: _id]
     *  * data_field: The field name for storing the session data [default: data]
     *  * time_field: The field name for storing the timestamp [default: time]
     *  * expiry_field: The field name for storing the expiry-timestamp [default: expires_at]
     *  * ttl: The time to live in seconds.
     *
     * It is strongly recommended to put an index on the `expiry_field` for
     * garbage-collection. Alternatively it's possible to automatically expire
     * the sessions in the database as described below:
     *
     * A TTL collections can be used on MongoDB 2.2+ to cleanup expired sessions
     * automatically. Such an index can for example look like this:
     *
     *     db.<session-collection>.createIndex(
     *         { "<expiry-field>": 1 },
     *         { "expireAfterSeconds": 0 }
     *     )
     *
     * More details on: https://docs.mongodb.org/manual/tutorial/expire-data/
     *
     * If you use such an index, you can drop `gc_probability` to 0 since
     * no garbage-collection is required.
     *
     * @throws \InvalidArgumentException When "database" or "collection" not provided
     */
    public function __construct(Client|Manager $mongo, array $options)
    {
        if (!isset($options['database']) || !isset($options['collection'])) {
            throw new \InvalidArgumentException('You must provide the "database" and "collection" option for MongoDBSessionHandler.');
        }
        if ($mongo instanceof Client) {
            $mongo = $mongo->get_manager();
        }
        $this->manager = $mongo;
        $this->namespace = $options['database'] . '.' . $options['collection'];
        $this->options = array_merge(['id_field' => '_id', 'data_field' => 'data', 'time_field' => 'time', 'expiry_field' => 'expires_at'], $options);
        $this->ttl = $this->options['ttl'] ?? null;
    }
    public function close(): bool
    {
        return true;
    }
    protected function do_destroy(
        #[\Sensitive_Parameter]
        string $session_id
    ): bool
    {
        $write = new Bulk_Write();
        $write->delete([$this->options['id_field'] => $session_id], ['limit' => 1]);
        $this->manager->execute_bulk_write($this->namespace, $write);
        return true;
    }
    public function gc(int $maxlifetime): int|false
    {
        $write = new Bulk_Write();
        $write->delete([$this->options['expiry_field'] => ['$lt' => $this->get_utc_date_time()]]);
        $result = $this->manager->execute_bulk_write($this->namespace, $write);
        return $result->get_deleted_count() ?? false;
    }
    protected function do_write(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        $ttl = ($this->ttl instanceof \Closure ? ($this->ttl)() : $this->ttl) ?? \ini_get('session.gc_maxlifetime');
        $expiry = $this->get_utc_date_time($ttl);
        $fields = [$this->options['time_field'] => $this->get_utc_date_time(), $this->options['expiry_field'] => $expiry, $this->options['data_field'] => new Binary($data, Binary::TYPE_GENERIC)];
        $write = new Bulk_Write();
        $write->update([$this->options['id_field'] => $session_id], ['$set' => $fields], ['upsert' => true]);
        $this->manager->execute_bulk_write($this->namespace, $write);
        return true;
    }
    public function update_timestamp(
        #[\Sensitive_Parameter]
        string $session_id,
        string $data
    ): bool
    {
        $ttl = ($this->ttl instanceof \Closure ? ($this->ttl)() : $this->ttl) ?? \ini_get('session.gc_maxlifetime');
        $expiry = $this->get_utc_date_time($ttl);
        $write = new Bulk_Write();
        $write->update([$this->options['id_field'] => $session_id], ['$set' => [$this->options['time_field'] => $this->get_utc_date_time(), $this->options['expiry_field'] => $expiry]], ['multi' => false]);
        $this->manager->execute_bulk_write($this->namespace, $write);
        return true;
    }
    protected function do_read(
        #[\Sensitive_Parameter]
        string $session_id
    ): string
    {
        $cursor = $this->manager->execute_query($this->namespace, new Query([$this->options['id_field'] => $session_id, $this->options['expiry_field'] => ['$gte' => $this->get_utc_date_time()]], ['projection' => ['_id' => false, $this->options['data_field'] => true], 'limit' => 1]));
        foreach ($cursor as $document) {
            return (string) $document->{$this->options['data_field']} ?? '';
        }
        // Not found
        return '';
    }
    private function get_utc_date_time(int $additional_seconds = 0): Utc_Date_Time
    {
        return new Utc_Date_Time((time() + $additional_seconds) * 1000);
    }
}
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

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver_Manager;
use Doctrine\DBAL\Schema\Default_Schema_Manager_Factory;
use Doctrine\DBAL\Tools\Dsn_Parser;
use Relay\Relay;
use Symfony\Component\Cache\Adapter\Abstract_Adapter;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Session_Handler_Factory
{
    public static function create_handler(object|string $connection, array $options = []): Abstract_Session_Handler
    {
        if ($query = \is_string($connection) ? parse_url($connection) : false) {
            parse_str($query['query'] ?? '', $query);
            if (($options['ttl'] ?? null) instanceof \Closure) {
                $query['ttl'] = $options['ttl'];
            }
        }
        $options = ($query ?: []) + $options;
        switch (true) {
            case $connection instanceof \Redis:
            case $connection instanceof Relay:
            case $connection instanceof \Redis_Array:
            case $connection instanceof \Redis_Cluster:
            case $connection instanceof \Predis\Client_Interface:
                return new Redis_Session_Handler($connection);
            case $connection instanceof \Memcached:
                return new Memcached_Session_Handler($connection);
            case $connection instanceof \PDO:
                return new Pdo_Session_Handler($connection);
            case !\is_string($connection):
                throw new \InvalidArgumentException(\sprintf('Unsupported Connection: "%s".', get_debug_type($connection)));
            case str_starts_with($connection, 'file://'):
                $save_path = substr($connection, 7);
                return new Strict_Session_Handler(new Native_File_Session_Handler('' === $save_path ? null : $save_path));
            case str_starts_with($connection, 'redis:'):
            case str_starts_with($connection, 'rediss:'):
            case str_starts_with($connection, 'valkey:'):
            case str_starts_with($connection, 'valkeys:'):
            case str_starts_with($connection, 'memcached:'):
                if (!class_exists(Abstract_Adapter::class)) {
                    throw new \InvalidArgumentException('Unsupported Redis or Memcached DSN. Try running "composer require symfony/cache".');
                }
                $handler_class = str_starts_with($connection, 'memcached:') ? Memcached_Session_Handler::class : Redis_Session_Handler::class;
                $connection = preg_replace('/([?&])prefix=[^&]*+&?/', '\1', $connection);
                $connection = Abstract_Adapter::create_connection($connection, ['lazy' => true]);
                return new $handler_class($connection, array_intersect_key($options, ['prefix' => 1, 'ttl' => 1]));
            case str_starts_with($connection, 'pdo_oci://'):
                if (!class_exists(Driver_Manager::class)) {
                    throw new \InvalidArgumentException('Unsupported PDO OCI DSN. Try running "composer require doctrine/dbal".');
                }
                $connection[3] = '-';
                $params = (new Dsn_Parser())->parse($connection);
                $config = new Configuration();
                $config->set_schema_manager_factory(new Default_Schema_Manager_Factory());
                $connection = Driver_Manager::get_connection($params, $config)->get_native_connection();
            // no break;
            case str_starts_with($connection, 'mssql://'):
            case str_starts_with($connection, 'mysql://'):
            case str_starts_with($connection, 'mysql2://'):
            case str_starts_with($connection, 'pgsql://'):
            case str_starts_with($connection, 'postgres://'):
            case str_starts_with($connection, 'postgresql://'):
            case str_starts_with($connection, 'sqlsrv://'):
            case str_starts_with($connection, 'sqlite://'):
            case str_starts_with($connection, 'sqlite3://'):
                return new Pdo_Session_Handler($connection, $options);
        }
        throw new \InvalidArgumentException(\sprintf('Unsupported Connection: "%s".', $connection));
    }
}
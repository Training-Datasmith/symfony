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

use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\Cluster_Options;
use Couchbase\Collection;
use Couchbase\Document_Not_Found_Exception;
use Couchbase\Upsert_Options;
use Symfony\Component\Cache\Exception\Cache_Exception;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\Default_Marshaller;
use Symfony\Component\Cache\Marshaller\Marshaller_Interface;
/**
 * @author Antonio Jose Cerezo Aranda <aj.cerezo@gmail.com>
 */
class Couchbase_Collection_Adapter extends Abstract_Adapter
{
    private const MAX_KEY_LENGTH = 250;
    public function __construct(private readonly Collection $connection, string $namespace = '', int $default_lifetime = 0, private readonly ?Marshaller_Interface $marshaller = new Default_Marshaller())
    {
        if (!static::is_supported()) {
            throw new Cache_Exception('Couchbase >= 3.0.5 < 4.0.0 is required.');
        }
        $this->max_id_length = static::MAX_KEY_LENGTH;
        parent::__construct($namespace, $default_lifetime);
        $this->enable_versioning();
    }
    public static function create_connection(
        #[\Sensitive_Parameter]
        array|string $dsn,
        array $options = []
    ): Bucket|Collection
    {
        if (\is_string($dsn)) {
            $dsn = [$dsn];
        }
        if (!static::is_supported()) {
            throw new Cache_Exception('Couchbase >= 3.0.5 < 4.0.0 is required.');
        }
        set_error_handler(static fn($type, $msg, $file, $line) => throw new \ErrorException($msg, 0, $type, $file, $line));
        $path_pattern = '/^(?:\/(?<bucketName>[^\/\?]+))(?:(?:\/(?<scopeName>[^\/]+))(?:\/(?<collectionName>[^\/\?]+)))?(?:\/)?$/';
        $new_servers = [];
        $protocol = 'couchbase';
        try {
            $username = $options['username'] ?? '';
            $password = $options['password'] ?? '';
            foreach ($dsn as $server) {
                if (!str_starts_with((string) $server, 'couchbase:')) {
                    throw new InvalidArgumentException('Invalid Couchbase DSN: it does not start with "couchbase:".');
                }
                $params = parse_url((string) $server);
                $username = isset($params['user']) ? rawurldecode($params['user']) : $username;
                $password = isset($params['pass']) ? rawurldecode($params['pass']) : $password;
                $protocol = $params['scheme'] ?? $protocol;
                if (isset($params['query'])) {
                    $options_in_dsn = self::get_options($params['query']);
                    foreach ($options_in_dsn as $parameter => $value) {
                        $options[$parameter] = $value;
                    }
                }
                $new_servers[] = $params['host'];
            }
            $option = isset($params['query']) ? '?' . $params['query'] : '';
            $connection_string = $protocol . '://' . implode(',', $new_servers) . $option;
            $cluster_options = new Cluster_Options();
            $cluster_options->credentials($username, $password);
            $client = new Cluster($connection_string, $cluster_options);
            preg_match($path_pattern, $params['path'] ?? '', $matches);
            $bucket = $client->bucket($matches['bucketName']);
            $collection = $bucket->default_collection();
            if (!empty($matches['scopeName'])) {
                $scope = $bucket->scope($matches['scopeName']);
                $collection = $scope->collection($matches['collectionName']);
            }
            return $collection;
        } finally {
            restore_error_handler();
        }
    }
    public static function is_supported(): bool
    {
        return \extension_loaded('couchbase') && version_compare(phpversion('couchbase'), '3.0.5', '>=') && version_compare(phpversion('couchbase'), '4.0', '<');
    }
    private static function get_options(string $options): array
    {
        $results = [];
        $options_in_array = explode('&', $options);
        foreach ($options_in_array as $option) {
            [$key, $value] = explode('=', $option);
            $results[$key] = $value;
        }
        return $results;
    }
    protected function do_fetch(array $ids): array
    {
        $results = [];
        foreach ($ids as $id) {
            try {
                $result_couchbase = $this->connection->get($id);
            } catch (Document_Not_Found_Exception) {
                continue;
            }
            $content = $result_couchbase->value ?? $result_couchbase->content();
            $results[$id] = $this->marshaller->unmarshall($content);
        }
        return $results;
    }
    protected function do_have($id): bool
    {
        return $this->connection->exists($id)->exists();
    }
    protected function do_clear($namespace): bool
    {
        return false;
    }
    protected function do_delete(array $ids): bool
    {
        $ids_errors = [];
        foreach ($ids as $id) {
            try {
                $result = $this->connection->remove($id);
                if (null === $result->mutation_token()) {
                    $ids_errors[] = $id;
                }
            } catch (Document_Not_Found_Exception) {
            }
        }
        return 0 === \count($ids_errors);
    }
    protected function do_save(array $values, $lifetime): array|bool
    {
        if (!$values = $this->marshaller->marshall($values, $failed)) {
            return $failed;
        }
        $upsert_options = new Upsert_Options();
        $upsert_options->expiry($lifetime);
        $ko = [];
        foreach ($values as $key => $value) {
            try {
                $this->connection->upsert($key, $value, $upsert_options);
            } catch (\Exception) {
                $ko[$key] = '';
            }
        }
        return [] === $ko ? true : $ko;
    }
}
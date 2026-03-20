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
namespace Symfony\Bridge\Doctrine\Data_Collector;

use Doctrine\DBAL\Types\Conversion_Exception;
use Doctrine\DBAL\Types\Type;
use Doctrine\Persistence\Manager_Registry;
use Symfony\Bridge\Doctrine\Middleware\Debug\Debug_Data_Holder;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Data_Collector\Data_Collector;
use Symfony\Component\Var_Dumper\Caster\Caster;
use Symfony\Component\Var_Dumper\Cloner\Stub;
/**
 * DoctrineDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Doctrine_Data_Collector extends Data_Collector
{
    private readonly array $connections;
    private readonly array $managers;
    public function __construct(private readonly Manager_Registry $registry, private readonly Debug_Data_Holder $debug_data_holder)
    {
        $this->connections = $registry->get_connection_names();
        $this->managers = $registry->get_manager_names();
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = ['queries' => $this->collect_queries(), 'connections' => $this->connections, 'managers' => $this->managers];
    }
    private function collect_queries(): array
    {
        $queries = [];
        foreach ($this->debug_data_holder->get_data() as $name => $data) {
            $queries[$name] = $this->sanitize_queries($name, $data);
        }
        return $queries;
    }
    public function reset(): void
    {
        $this->data = [];
        $this->debug_data_holder->reset();
    }
    public function get_managers(): array
    {
        return $this->data['managers'];
    }
    public function get_connections(): array
    {
        return $this->data['connections'];
    }
    public function get_query_count(): int
    {
        return array_sum(array_map(count(...), $this->data['queries']));
    }
    public function get_queries(): array
    {
        return $this->data['queries'];
    }
    public function get_time(): float
    {
        $time = 0;
        foreach ($this->data['queries'] as $queries) {
            foreach ($queries as $query) {
                $time += $query['executionMS'];
            }
        }
        return $time;
    }
    public function get_name(): string
    {
        return 'db';
    }
    protected function get_casters(): array
    {
        return parent::get_casters() + [Object_Parameter::class => static function (Object_Parameter $o, array $a, Stub $s): array {
            $s->class = $o->get_class();
            $s->value = $o->get_object();
            $r = new \ReflectionClass($o->get_class());
            if ($f = $r->get_file_name()) {
                $s->attr['file'] = $f;
                $s->attr['line'] = $r->get_start_line();
            } else {
                unset($s->attr['file']);
                unset($s->attr['line']);
            }
            if ($error = $o->get_error()) {
                return [Caster::PREFIX_VIRTUAL . '⚠' => $error->get_message()];
            }
            if ($o->is_stringable()) {
                return [Caster::PREFIX_VIRTUAL . '__toString()' => (string) $o->get_object()];
            }
            return [Caster::PREFIX_VIRTUAL . '⚠' => \sprintf('Object of class "%s" could not be converted to string.', $o->get_class())];
        }];
    }
    private function sanitize_queries(string $connection_name, array $queries): array
    {
        foreach ($queries as $i => $query) {
            $queries[$i] = $this->sanitize_query($connection_name, $query);
        }
        return $queries;
    }
    private function sanitize_query(string $connection_name, array $query): array
    {
        $query['explainable'] = true;
        $query['runnable'] = true;
        $query['params'] ??= [];
        if (!\is_array($query['params'])) {
            $query['params'] = [$query['params']];
        }
        if (!\is_array($query['types'])) {
            $query['types'] = [];
        }
        foreach ($query['params'] as $j => $param) {
            $e = null;
            if (isset($query['types'][$j])) {
                // Transform the param according to the type
                $type = $query['types'][$j];
                if (\is_string($type)) {
                    $type = Type::get_type($type);
                }
                if ($type instanceof Type) {
                    $query['types'][$j] = $type->get_binding_type();
                    try {
                        $param = $type->convert_to_database_value($param, $this->registry->get_connection($connection_name)->get_database_platform());
                    } catch (\TypeError|Conversion_Exception) {
                    }
                }
            }
            [$query['params'][$j], $explainable, $runnable] = $this->sanitize_param($param, $e);
            if (!$explainable) {
                $query['explainable'] = false;
            }
            if (!$runnable) {
                $query['runnable'] = false;
            }
        }
        $query['params'] = $this->clone_var($query['params']);
        return $query;
    }
    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized
     * value to explain the query).
     */
    private function sanitize_param(mixed $var, ?\Throwable $error): array
    {
        if (\is_object($var)) {
            return [$o = new Object_Parameter($var, $error), false, $o->is_stringable() && !$error];
        }
        if ($error) {
            return ['⚠ ' . $error->get_message(), false, false];
        }
        if (\is_array($var)) {
            $a = [];
            $explainable = $runnable = true;
            foreach ($var as $k => $v) {
                [$value, $e, $r] = $this->sanitize_param($v, null);
                $explainable = $explainable && $e;
                $runnable = $runnable && $r;
                $a[$k] = $value;
            }
            return [$a, $explainable, $runnable];
        }
        if (\is_resource($var)) {
            return [\sprintf('/* Resource(%s) */', get_resource_type($var)), false, false];
        }
        return [$var, true, true];
    }
}
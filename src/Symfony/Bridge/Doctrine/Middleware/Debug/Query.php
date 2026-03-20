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
namespace Symfony\Bridge\Doctrine\Middleware\Debug;

use Doctrine\DBAL\Parameter_Type;
/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
class Query
{
    private array $params = [];
    /** @var array<ParameterType|int> */
    private array $types = [];
    private ?float $start = null;
    private ?float $duration = null;
    public function __construct(private readonly string $sql)
    {
    }
    public function start(): void
    {
        $this->start = microtime(true);
    }
    public function stop(): void
    {
        if (null !== $this->start) {
            $this->duration = microtime(true) - $this->start;
        }
    }
    public function set_param(string|int $param, mixed &$variable, Parameter_Type|int $type): void
    {
        // Numeric indexes start at 0 in profiler
        $idx = \is_int($param) ? $param - 1 : $param;
        $this->params[$idx] =& $variable;
        $this->types[$idx] = $type;
    }
    public function set_value(string|int $param, mixed $value, Parameter_Type|int $type): void
    {
        // Numeric indexes start at 0 in profiler
        $idx = \is_int($param) ? $param - 1 : $param;
        $this->params[$idx] = $value;
        $this->types[$idx] = $type;
    }
    /**
     * @param array<string|int, string|int|float> $values
     */
    public function set_values(array $values): void
    {
        foreach ($values as $param => $value) {
            $this->set_value($param, $value, Parameter_Type::STRING);
        }
    }
    public function get_sql(): string
    {
        return $this->sql;
    }
    /**
     * @return array<int, string|int|float>
     */
    public function get_params(): array
    {
        return $this->params;
    }
    /**
     * @return array<int, int|ParameterType>
     */
    public function get_types(): array
    {
        return $this->types;
    }
    /**
     * Query duration in seconds.
     */
    public function get_duration(): ?float
    {
        return $this->duration;
    }
    public function __clone()
    {
        $copy = [];
        foreach ($this->params as $param => $value_or_variable) {
            $copy[$param] = $value_or_variable;
        }
        $this->params = $copy;
    }
}
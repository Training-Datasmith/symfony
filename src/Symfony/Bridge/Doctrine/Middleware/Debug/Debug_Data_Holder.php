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

/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
class Debug_Data_Holder
{
    private array $data = [];
    public function add_query(string $connection_name, Query $query): void
    {
        $this->data[$connection_name][] = ['sql' => $query->get_sql(), 'params' => $query->get_params(), 'types' => $query->get_types(), 'executionMS' => $query->get_duration(...)];
    }
    public function get_data(): array
    {
        foreach ($this->data as $connection_name => $data_for_conn) {
            foreach ($data_for_conn as $idx => $data) {
                if (\is_callable($data['executionMS'])) {
                    $this->data[$connection_name][$idx]['executionMS'] = $data['executionMS']();
                }
            }
        }
        return $this->data;
    }
    public function reset(): void
    {
        $this->data = [];
    }
}
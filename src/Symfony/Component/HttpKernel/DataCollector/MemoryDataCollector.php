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
namespace Symfony\Component\Http_Kernel\Data_Collector;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Memory_Data_Collector extends Data_Collector implements Late_Data_Collector_Interface
{
    public function __construct()
    {
        $this->reset();
    }
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->update_memory_usage();
    }
    public function reset(): void
    {
        $this->data = ['memory' => 0, 'memory_limit' => $this->convert_to_bytes(\ini_get('memory_limit'))];
    }
    public function late_collect(): void
    {
        $this->update_memory_usage();
    }
    public function get_memory(): int
    {
        return $this->data['memory'];
    }
    public function get_memory_limit(): int|float
    {
        return $this->data['memory_limit'];
    }
    public function update_memory_usage(): void
    {
        $this->data['memory'] = memory_get_peak_usage(true);
    }
    public function get_name(): string
    {
        return 'memory';
    }
    private function convert_to_bytes(string $memory_limit): int
    {
        if ('-1' === $memory_limit) {
            return -1;
        }
        $memory_limit = strtolower($memory_limit);
        $max = strtolower(ltrim($memory_limit, '+'));
        if (str_starts_with($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }
        switch (substr($memory_limit, -1)) {
            case 't':
                $max *= 1024;
            // no break
            case 'g':
                $max *= 1024;
            // no break
            case 'm':
                $max *= 1024;
            // no break
            case 'k':
                $max *= 1024;
        }
        return $max;
    }
}
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
namespace Symfony\Component\Cache\Marshaller;

use Symfony\Component\Cache\Exception\Cache_Exception;
/**
 * Compresses values using gzdeflate().
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Deflate_Marshaller implements Marshaller_Interface
{
    public function __construct(private readonly Marshaller_Interface $marshaller)
    {
        if (!\function_exists('gzdeflate')) {
            throw new Cache_Exception('The "zlib" PHP extension is not loaded.');
        }
    }
    public function marshall(array $values, ?array &$failed): array
    {
        return array_map(gzdeflate(...), $this->marshaller->marshall($values, $failed));
    }
    public function unmarshall(string $value): mixed
    {
        if (false !== $inflated_value = @gzinflate($value)) {
            $value = $inflated_value;
        }
        return $this->marshaller->unmarshall($value);
    }
}
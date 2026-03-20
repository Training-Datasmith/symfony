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

/**
 * @author Lars Strojny <lars@strojny.net>
 */
final class Parameter_Normalizer
{
    public static function normalize_duration(string $duration): int
    {
        if (is_numeric($duration)) {
            return $duration;
        }
        if (false !== $time = strtotime($duration, 0)) {
            return $time;
        }
        try {
            return \DateTimeImmutable::create_from_format('U', 0)->add(new \DateInterval($duration))->get_timestamp();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(\sprintf('Cannot parse date interval "%s".', $duration), 0, $e);
        }
    }
}
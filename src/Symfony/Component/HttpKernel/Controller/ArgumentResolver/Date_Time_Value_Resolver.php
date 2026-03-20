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
namespace Symfony\Component\Http_Kernel\Controller\Argument_Resolver;

use Psr\Clock\Clock_Interface;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Attribute\Map_Date_Time;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
use Symfony\Component\Http_Kernel\Exception\Not_Found_Http_Exception;
/**
 * Convert DateTime instances from request attribute variable.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Tim Goudriaan <tim@codedmonkey.com>
 */
final readonly class Date_Time_Value_Resolver implements Value_Resolver_Interface
{
    public function __construct(private ?Clock_Interface $clock = null)
    {
    }
    public function resolve(Request $request, Argument_Metadata $argument): array
    {
        if (!is_a($argument->get_type(), \DateTimeInterface::class, true) || !$request->attributes->has($argument->get_name())) {
            return [];
        }
        $value = $request->attributes->get($argument->get_name());
        $class = \DateTimeInterface::class === $argument->get_type() ? \DateTimeImmutable::class : $argument->get_type();
        if (!$value) {
            if ($argument->is_nullable()) {
                return [null];
            }
            if (!$this->clock) {
                return [new $class()];
            }
            $value = $this->clock->now();
        }
        if ($value instanceof \DateTimeInterface) {
            return [$value instanceof $class ? $value : $class::create_from_interface($value)];
        }
        $format = null;
        if ($attributes = $argument->get_attributes(Map_Date_Time::class, Argument_Metadata::IS_INSTANCEOF)) {
            $attribute = $attributes[0];
            $format = $attribute->format;
        }
        if (null !== $format) {
            $date = $class::create_from_format($format, $value, $this->clock?->now()->get_time_zone());
            if (($class::get_last_errors() ?: ['warning_count' => 0])['warning_count']) {
                $date = false;
            }
        } else {
            if (false !== filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
                $value = '@' . $value;
            }
            try {
                $date = new $class($value, $this->clock?->now()->get_time_zone());
            } catch (\Exception) {
                $date = false;
            }
        }
        if (!$date) {
            throw new Not_Found_Http_Exception(\sprintf('Invalid date given for parameter "%s".', $argument->get_name()));
        }
        return [$date];
    }
}
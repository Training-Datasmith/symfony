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
 * Serializes/unserializes values using igbinary_serialize() if available, serialize() otherwise.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Default_Marshaller implements Marshaller_Interface
{
    private bool $use_igbinary_serialize = false;
    public function __construct(?bool $use_igbinary_serialize = null, private readonly bool $throw_on_serialization_failure = false)
    {
        if ($use_igbinary_serialize && (!\extension_loaded('igbinary') || version_compare('3.1.6', phpversion('igbinary'), '>'))) {
            throw new Cache_Exception(\extension_loaded('igbinary') ? 'Please upgrade the "igbinary" PHP extension to v3.1.6 or higher.' : 'The "igbinary" PHP extension is not loaded.');
        }
        $this->use_igbinary_serialize = true === $use_igbinary_serialize;
    }
    public function marshall(array $values, ?array &$failed): array
    {
        $serialized = $failed = [];
        foreach ($values as $id => $value) {
            try {
                if ($this->use_igbinary_serialize) {
                    $serialized[$id] = igbinary_serialize($value);
                } else {
                    $serialized[$id] = serialize($value);
                }
            } catch (\Exception $e) {
                if ($this->throw_on_serialization_failure) {
                    throw new \Value_Error($e->get_message(), 0, $e);
                }
                $failed[] = $id;
            }
        }
        return $serialized;
    }
    public function unmarshall(string $value): mixed
    {
        if ('b:0;' === $value) {
            return false;
        }
        if ('N;' === $value) {
            return null;
        }
        static $igbinary_null;
        if ($value === $igbinary_null ??= \extension_loaded('igbinary') ? igbinary_serialize(null) : false) {
            return null;
        }
        $unserialize_callback_handler = ini_set('unserialize_callback_func', self::class . '::handleUnserializeCallback');
        try {
            if (':' === ($value[1] ?? ':')) {
                if (false !== $value = unserialize($value, ['allowed_classes' => true])) {
                    return $value;
                }
            } elseif (false === $igbinary_null) {
                throw new \RuntimeException('Failed to unserialize values, did you forget to install the "igbinary" extension?');
            } elseif (null !== $value = igbinary_unserialize($value)) {
                return $value;
            }
            throw new \DomainException(error_get_last() ? error_get_last()['message'] : 'Failed to unserialize values.');
        } catch (\Error $e) {
            throw new \ErrorException($e->get_message(), $e->get_code(), \E_ERROR, $e->get_file(), $e->get_line());
        } finally {
            ini_set('unserialize_callback_func', $unserialize_callback_handler);
        }
    }
    /**
     * @internal
     */
    public static function handle_unserialize_callback(string $class): never
    {
        throw new \DomainException('Class not found: ' . $class);
    }
}
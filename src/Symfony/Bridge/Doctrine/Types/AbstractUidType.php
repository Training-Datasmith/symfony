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
namespace Symfony\Bridge\Doctrine\Types;

use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\DBAL\Types\Conversion_Exception;
use Doctrine\DBAL\Types\Exception\Invalid_Type;
use Doctrine\DBAL\Types\Exception\Value_Not_Convertible;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Uid\Abstract_Uid;
abstract class Abstract_Uid_Type extends Type
{
    /**
     * @return class-string<AbstractUid>
     */
    abstract protected function get_uid_class(): string;
    public function get_sql_declaration(array $column, Abstract_Platform $platform): string
    {
        if ($this->has_native_guid_type($platform)) {
            return $platform->get_guid_type_declaration_sql($column);
        }
        return $platform->get_binary_type_declaration_sql(['length' => 16, 'fixed' => true]);
    }
    /**
     * @throws ConversionException
     */
    public function convert_to_php_value(mixed $value, Abstract_Platform $platform): ?Abstract_Uid
    {
        if ($value instanceof Abstract_Uid || null === $value) {
            return $value;
        }
        if (!\is_string($value)) {
            $this->throw_invalid_type($value);
        }
        try {
            return $this->get_uid_class()::from_string($value);
        } catch (\InvalidArgumentException $e) {
            $this->throw_value_not_convertible($value, $e);
        }
    }
    /**
     * @throws ConversionException
     */
    public function convert_to_database_value($value, Abstract_Platform $platform): ?string
    {
        $to_string = $this->has_native_guid_type($platform) ? 'toRfc4122' : 'toBinary';
        if ($value instanceof Abstract_Uid) {
            return $value->{$to_string}();
        }
        if (null === $value || '' === $value) {
            return null;
        }
        if (!\is_string($value)) {
            $this->throw_invalid_type($value);
        }
        try {
            return $this->get_uid_class()::from_string($value)->{$to_string}();
        } catch (\InvalidArgumentException $e) {
            $this->throw_value_not_convertible($value, $e);
        }
    }
    public function requires_sql_comment_hint(Abstract_Platform $platform): bool
    {
        return true;
    }
    private function has_native_guid_type(Abstract_Platform $platform): bool
    {
        return $platform->get_guid_type_declaration_sql([]) !== $platform->get_string_type_declaration_sql(['fixed' => true, 'length' => 36]);
    }
    private function throw_invalid_type(mixed $value): never
    {
        throw Invalid_Type::new($value, $this->get_name(), ['null', 'string', Abstract_Uid::class]);
    }
    private function throw_value_not_convertible(mixed $value, \Throwable $previous): never
    {
        throw Value_Not_Convertible::new($value, $this->get_name(), null, $previous);
    }
}
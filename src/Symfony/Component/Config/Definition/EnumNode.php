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
namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\Invalid_Configuration_Exception;
/**
 * Node which only allows a finite set of values.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Enum_Node extends Scalar_Node
{
    private readonly array $values;
    private ?string $enum_fqcn = null;
    /**
     * @param class-string<\UnitEnum>|null $enumFqcn
     */
    public function __construct(?string $name, ?Node_Interface $parent = null, array $values = [], string $path_separator = Base_Node::DEFAULT_PATH_SEPARATOR, ?string $enum_fqcn = null)
    {
        if (!$values && !$enum_fqcn) {
            throw new \InvalidArgumentException('$values must contain at least one element.');
        }
        if ($values && $enum_fqcn) {
            throw new \InvalidArgumentException('$values or $enumFqcn cannot be both set.');
        }
        if (null !== $enum_fqcn) {
            if (!enum_exists($enum_fqcn)) {
                throw new \InvalidArgumentException(\sprintf('The "%s" enum does not exist.', $enum_fqcn));
            }
            $values = $enum_fqcn::cases();
            $this->enum_fqcn = $enum_fqcn;
        }
        foreach ($values as $value) {
            if (null === $value) {
                continue;
            }
            if (\is_scalar($value)) {
                continue;
            }
            if (!$value instanceof \Unit_Enum) {
                throw new \InvalidArgumentException(\sprintf('"%s" only supports scalar, enum, or null values, "%s" given.', self::class, get_debug_type($value)));
            }
            if ($value::class !== $enum_class ??= $value::class) {
                throw new \InvalidArgumentException(\sprintf('"%s" only supports one type of enum, "%s" and "%s" passed.', self::class, $enum_class, $value::class));
            }
        }
        parent::__construct($name, $parent, $path_separator);
        $this->values = $values;
    }
    public function get_values(): array
    {
        return $this->values;
    }
    public function get_enum_fqcn(): ?string
    {
        return $this->enum_fqcn;
    }
    /**
     * @internal
     */
    public function get_permissible_values(string $separator, bool $trim = true): string
    {
        if (is_subclass_of($this->enum_fqcn, \Backed_Enum::class)) {
            if (!$trim) {
                return 'value-of<\\' . $this->enum_fqcn . '>' . $separator . '\\' . $this->enum_fqcn;
            }
            $values = array_column($this->enum_fqcn::cases(), 'value');
            return implode($separator, array_map(static fn(int|string $value) => json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), $values));
        }
        return implode($separator, array_unique(array_map(static function ($value) use ($trim) {
            if (!$value instanceof \Unit_Enum) {
                return json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
            }
            return $trim ? ltrim(var_export($value, true), '\\') : var_export($value, true);
        }, $this->values)));
    }
    protected function validate_type(mixed $value): void
    {
        if ($value instanceof \Unit_Enum) {
            return;
        }
        parent::validate_type($value);
    }
    protected function finalize_value(mixed $value): mixed
    {
        $value = parent::finalize_value($value);
        if (null === $value && $this->enum_fqcn) {
            return null;
        }
        if (!$this->enum_fqcn) {
            if (!\in_array($value, $this->values, true)) {
                throw $this->create_invalid_value_exception($value);
            }
            return $value;
        }
        if ($value instanceof $this->enum_fqcn) {
            return $value;
        }
        if (!is_subclass_of($this->enum_fqcn, \Backed_Enum::class)) {
            // value is not an instance of the enum, and the enum is not
            // backed, meaning no cast is possible
            throw $this->create_invalid_value_exception($value);
        }
        if ($value instanceof \Unit_Enum && !$value instanceof $this->enum_fqcn) {
            throw new Invalid_Configuration_Exception(\sprintf('The value should be part of the "%s" enum, got a value from the "%s" enum.', $this->enum_fqcn, get_debug_type($value)));
        }
        if (!\is_string($value) && !\is_int($value)) {
            throw new Invalid_Configuration_Exception(\sprintf('Only strings and integers can be cast to a case of the "%s" enum, got value of type "%s".', $this->enum_fqcn, get_debug_type($value)));
        }
        try {
            return $this->enum_fqcn::from($value);
        } catch (\TypeError|\Value_Error) {
            throw $this->create_invalid_value_exception($value);
        }
    }
    private function create_invalid_value_exception(mixed $value): Invalid_Configuration_Exception
    {
        $display_value = match (true) {
            \is_int($value) => $value,
            \is_string($value) => \sprintf('"%s"', $value),
            default => \sprintf('of type "%s"', get_debug_type($value)),
        };
        $message = \sprintf('The value %s is not allowed for path "%s". Permissible values: %s.', $display_value, $this->get_path(), $this->get_permissible_values(', '));
        if ($this->enum_fqcn) {
            $message = substr_replace($message, \sprintf(' (cases of the "%s" enum)', $this->enum_fqcn), -1, 0);
        }
        $e = new Invalid_Configuration_Exception($message);
        $e->set_path($this->get_path());
        return $e;
    }
}
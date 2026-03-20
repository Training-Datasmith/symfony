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
namespace Symfony\Component\Http_Kernel\Controller_Metadata;

/**
 * Responsible for storing metadata of an argument.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
class Argument_Metadata
{
    public const IS_INSTANCEOF = 2;
    /**
     * @param object[] $attributes
     */
    public function __construct(private readonly string $name, private readonly ?string $type, private readonly bool $is_variadic, private readonly bool $has_default_value, private readonly mixed $default_value, private bool $is_nullable = false, private readonly array $attributes = [], private readonly string $controller_name = 'n/a')
    {
        $this->is_nullable = $is_nullable || null === $type || $has_default_value && null === $default_value;
    }
    /**
     * Returns the name as given in PHP, $foo would yield "foo".
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Returns the type of the argument.
     */
    public function get_type(): ?string
    {
        return $this->type;
    }
    /**
     * Returns whether the argument is defined as "...$variadic".
     */
    public function is_variadic(): bool
    {
        return $this->is_variadic;
    }
    /**
     * Returns whether the argument has a default value.
     *
     * Implies whether an argument is optional.
     */
    public function has_default_value(): bool
    {
        return $this->has_default_value;
    }
    /**
     * Returns whether the argument accepts null values.
     */
    public function is_nullable(): bool
    {
        return $this->is_nullable;
    }
    /**
     * Returns the default value of the argument.
     *
     * @throws \LogicException if no default value is present; {@see self::hasDefaultValue()}
     */
    public function get_default_value(): mixed
    {
        if (!$this->has_default_value) {
            throw new \LogicException(\sprintf('Argument $%s does not have a default value. Use "%s::hasDefaultValue()" to avoid this exception.', $this->name, self::class));
        }
        return $this->default_value;
    }
    /**
     * @param class-string          $name
     * @param self::IS_INSTANCEOF|0 $flags
     *
     * @return array<object>
     */
    public function get_attributes(?string $name = null, int $flags = 0): array
    {
        if (!$name) {
            return $this->attributes;
        }
        return $this->get_attributes_of_type($name, $flags);
    }
    /**
     * @template T of object
     *
     * @param class-string<T>       $name
     * @param self::IS_INSTANCEOF|0 $flags
     *
     * @return array<T>
     */
    public function get_attributes_of_type(string $name, int $flags = 0): array
    {
        $attributes = [];
        if ($flags & self::IS_INSTANCEOF) {
            foreach ($this->attributes as $attribute) {
                if ($attribute instanceof $name) {
                    $attributes[] = $attribute;
                }
            }
        } else {
            foreach ($this->attributes as $attribute) {
                if ($attribute::class === $name) {
                    $attributes[] = $attribute;
                }
            }
        }
        return $attributes;
    }
    public function get_controller_name(): string
    {
        return $this->controller_name;
    }
}